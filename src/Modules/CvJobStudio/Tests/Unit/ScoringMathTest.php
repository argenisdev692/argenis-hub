<?php

declare(strict_types=1);

use Modules\CvJobStudio\Domain\Enums\RemoteScope;
use Modules\CvJobStudio\Domain\Enums\ScoreBand;
use Modules\CvJobStudio\Domain\Exceptions\ProfileGateIncompleteException;
use Modules\CvJobStudio\Domain\Services\BandClassifier;
use Modules\CvJobStudio\Domain\Services\CapLadder;
use Modules\CvJobStudio\Domain\Services\DeterministicScorer;
use Modules\CvJobStudio\Domain\Services\DiscoveryScorer;
use Modules\CvJobStudio\Domain\Services\GateEvaluator;
use Modules\CvJobStudio\Domain\Services\HeuristicSkillScorer;
use Modules\CvJobStudio\Domain\Services\MatchScoreCalculator;
use Modules\CvJobStudio\Domain\Services\RemoteScopeClassifier;
use Modules\CvJobStudio\Domain\Services\RequirementWeigher;
use Modules\CvJobStudio\Domain\Services\SemanticScorer;
use Modules\CvJobStudio\Domain\Services\SkillRelationSet;

function studioRules(): array
{
    return config('cv-job-studio');
}

it('caps soft weights at 15 percent of the hard total', function (): void {
    $requirements = [
        ['tag' => 'required', 'nature' => 'hard'],
        ['tag' => 'required', 'nature' => 'hard'],
        ['tag' => 'preferred', 'nature' => 'soft'],
        ['tag' => 'preferred', 'nature' => 'soft'],
        ['tag' => 'preferred', 'nature' => 'soft'],
    ];

    $result = (new RequirementWeigher)->weigh($requirements, studioRules()['tag_weights'], (float) studioRules()['soft_cap_ratio']);

    // Hard 2.0; soft raw 1.8 capped to (0.15/0.85)·2.0 ≈ 0.3529.
    expect($result['hard_total'])->toBe(2.0)
        ->and($result['soft_total'])->toBeGreaterThan(0.35)->toBeLessThan(0.36)
        ->and(array_sum($result['weights']))->toBeGreaterThan(2.35)->toBeLessThan(2.36);
});

it('reproduces v1 exactly when context and position factors are 1', function (): void {
    $rows = [
        ['requirement' => 'Laravel', 'weight' => 1.0, 'evidence' => 'in_bullet', 'position_ratio' => 0.1],
        ['requirement' => 'Vue.js', 'weight' => 0.6, 'evidence' => 'both', 'position_ratio' => 0.2],
        ['requirement' => 'Kubernetes', 'weight' => 1.0, 'evidence' => 'list_only', 'position_ratio' => 0.9],
    ];
    $relations = new SkillRelationSet(['laravel' => true, 'vue.js' => true]);

    $plain = (new HeuristicSkillScorer)->score($rows, $relations, 1.0, 1.0);

    // (1.0·1 + 0.6·1 + 1.0·0) / 2.6 · 100.
    expect($plain['h'])->toBeGreaterThan(61.53)->toBeLessThan(61.55);

    $v2 = (new HeuristicSkillScorer)->score($rows, $relations, 0.85, 0.90);

    // Kubernetes scores 0 either way; v2 can only reduce, never inflate.
    expect($v2['h'])->toBeLessThanOrEqual($plain['h']);
});

it('grants family credit only through confirmed relations', function (): void {
    $rows = [
        ['requirement' => 'CI/CD', 'weight' => 1.0, 'evidence' => 'in_bullet', 'position_ratio' => 0.1],
    ];

    $pending = new SkillRelationSet(['github actions' => true]);
    $confirmed = new SkillRelationSet(
        ['github actions' => true],
        [['from' => 'CI/CD', 'to' => 'GitHub Actions', 'kind' => 'family']],
    );

    $without = (new HeuristicSkillScorer)->score($rows, $pending, 0.85, 0.90);
    $with = (new HeuristicSkillScorer)->score($rows, $confirmed, 0.85, 0.90);

    expect($without['h'])->toBe(0.0)->and($with['h'])->toBe(50.0);
});

it('calibrates cosine instead of collapsing it like (x+1)/2', function (): void {
    $scorer = new SemanticScorer;

    // Naive mapping puts 0.55 and 0.75 only 0.1 apart, both ≈ 0.8.
    expect((0.75 + 1) / 2 - ((0.55 + 1) / 2))->toBeGreaterThan(0.099)->toBeLessThan(0.101);

    // The calibrated norm separates them more than threefold.
    $spread = $scorer->normalize(0.75, 0.30, 0.85) - $scorer->normalize(0.55, 0.30, 0.85);

    expect($spread)->toBeGreaterThan(0.36);
});

it('renormalises D over readable signals only (D = 94 case)', function (): void {
    $result = (new DeterministicScorer)->score(
        ['experience' => 100, 'location' => 90, 'education' => 90, 'language' => 90],
        studioRules()['deterministic']['weights'],
    );

    expect($result['d'])->toBe(94.0);

    $partial = (new DeterministicScorer)->score(
        ['experience' => 100, 'location' => null, 'education' => null, 'language' => null],
        studioRules()['deterministic']['weights'],
    );

    expect($partial['d'])->toBe(100.0)->and($partial['unreadable'])->toBe(['location', 'education', 'language']);
});

it('rounds 69.55 to 70 but holds weak evidence at 69', function (): void {
    $ladder = new CapLadder;
    $caps = studioRules()['caps'];

    $free = $ladder->apply(69.55, ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true], $caps);
    $weak = $ladder->apply(69.55, ['readable' => true, 'credential_ok' => true, 'evidence_ok' => false], $caps);

    expect($free['total'])->toBe(70.0)->and($free['cap_reason'])->toBeNull()
        ->and($weak['total'])->toBe(69.0)->and($weak['cap_reason'])->toBe('weak_evidence');
});

it('applies the lowest applicable cap and keeps the uncapped value', function (): void {
    $result = (new CapLadder)->apply(
        82.4,
        ['readable' => false, 'credential_ok' => false, 'evidence_ok' => true],
        studioRules()['caps'],
    );

    expect($result['total'])->toBe(55.0)
        ->and($result['raw'])->toBe(82.4)
        ->and($result['cap_reason'])->toBe('unreadable_requirements');
});

it('classifies bands at 85/75/70/60', function (): void {
    $classifier = new BandClassifier;
    $bands = studioRules()['bands'];

    expect($classifier->classify(85, $bands))->toBe(ScoreBand::Strong)
        ->and($classifier->classify(84.9, $bands))->toBe(ScoreBand::Good)
        ->and($classifier->classify(70, $bands))->toBe(ScoreBand::Apply)
        ->and($classifier->classify(69.9, $bands))->toBe(ScoreBand::Consider)
        ->and($classifier->classify(59.9, $bands))->toBe(ScoreBand::Skip);
});

it('scores D_disc with the worked 0.8825 example', function (): void {
    $score = (new DiscoveryScorer)->score(
        ['p' => 0.9, 'k' => 0.85, 'r' => 0.9, 'f' => 0.85, 'n' => 0.9],
        studioRules()['shortlist']['weights'],
    );

    expect($score)->toBeGreaterThan(0.8824)->toBeLessThan(0.8826);
});

it('classifies remote scopes', function (): void {
    $classifier = new RemoteScopeClassifier;

    expect($classifier->classify('Senior Laravel Dev', 'Remote, worldwide'))->toBe(RemoteScope::RemoteGlobal)
        ->and($classifier->classify('Vue Developer', 'Remote within EU'))->toBe(RemoteScope::RemoteEu)
        ->and($classifier->classify('Fullstack Dev', 'Remote, Portugal'))->toBe(RemoteScope::RemotePtEs)
        ->and($classifier->classify('Backend Dev', 'Hybrid, Madrid'))->toBe(RemoteScope::HybridLocal)
        ->and($classifier->classify('PHP Developer', 'Lisbon'))->toBe(RemoteScope::RemoteUnclear);
});

it('fails gates with reasons and blocks runs with missing gate inputs', function (): void {
    $evaluator = new GateEvaluator;

    $verdicts = $evaluator->evaluate(
        ['remote_scope' => 'hybrid_local', 'title' => 'WordPress Developer', 'text' => 'WordPress Elementor role', 'url' => 'https://acme.com/jobs/1'],
        ['accepted_remote_scopes' => ['remote_eu'], 'stack_must' => ['Laravel'], 'stack_reject' => ['WordPress']],
    );

    expect($verdicts[0]->passed)->toBeFalse()
        ->and($verdicts[1]->passed)->toBeFalse()
        ->and($verdicts[1]->reasonCode)->toBe('stack_rejected')
        ->and($verdicts[2]->passed)->toBeTrue();

    try {
        $blocked = $evaluator->evaluate(
            ['remote_scope' => 'remote_eu', 'title' => 'x', 'text' => 'y', 'url' => 'https://acme.com/jobs/1'],
            ['accepted_remote_scopes' => [], 'stack_must' => [], 'stack_reject' => []],
        );

        expect($blocked)->toBeNull('Expected ProfileGateIncompleteException');
    } catch (ProfileGateIncompleteException $exception) {
        expect($exception->getMessage())->toContain('accepted_remote_scopes');
    }
});

it('computes identical totals for identical inputs (NFR-1)', function (): void {
    $calculator = new MatchScoreCalculator(
        new HeuristicSkillScorer, new SemanticScorer, new DeterministicScorer, new CapLadder, new BandClassifier,
    );
    $rules = [...studioRules(), 'rules_version' => 2];
    $relations = new SkillRelationSet(['laravel' => true]);
    $rows = [['requirement' => 'Laravel', 'weight' => 1.0, 'evidence' => 'in_bullet', 'position_ratio' => 0.1]];

    $first = $calculator->calculate($rows, $relations, ['title_cosine' => 0.8, 'responsibility_cosine' => 0.7], ['experience' => 90, 'location' => 80, 'education' => 70, 'language' => 60], ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true], $rules);
    $second = $calculator->calculate($rows, $relations, ['title_cosine' => 0.8, 'responsibility_cosine' => 0.7], ['experience' => 90, 'location' => 80, 'education' => 70, 'language' => 60], ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true], $rules);

    expect($second->totalScore)->toBe($first->totalScore)
        ->and($second->rawScore)->toBe($first->rawScore)
        ->and($second->band)->toBe($first->band);
});
