<?php

declare(strict_types=1);

use Modules\CvJobStudio\Domain\Enums\ScoreBand;
use Modules\CvJobStudio\Domain\Services\BandClassifier;
use Modules\CvJobStudio\Domain\Services\CapLadder;
use Modules\CvJobStudio\Domain\Services\DeterministicScorer;
use Modules\CvJobStudio\Domain\Services\GateEvaluator;
use Modules\CvJobStudio\Domain\Services\HeuristicSkillScorer;
use Modules\CvJobStudio\Domain\Services\MatchScoreCalculator;
use Modules\CvJobStudio\Domain\Services\OutcomeCorrelator;
use Modules\CvJobStudio\Domain\Services\SemanticScorer;
use Modules\CvJobStudio\Domain\Services\SkillRelationSet;

function calibrationRules(): array
{
    return [...config('cv-job-studio'), 'rules_version' => 2];
}

function calibrationCalculator(): MatchScoreCalculator
{
    return new MatchScoreCalculator(
        new HeuristicSkillScorer,
        new SemanticScorer,
        new DeterministicScorer,
        new CapLadder,
        new BandClassifier,
    );
}

/**
 * Reconstruction fixtures from the run notes in
 * `GUIDE/JOBORA-CLAUDE/fullstack cv/job-search-cache.json` (T-092/T-093).
 *
 * The cache holds URLs + judgements, not posting objects, so these fixtures
 * reconstruct the stated cases (documented, not independent validation):
 * every expectation below quotes the note it comes from.
 */
function noteCases(): array
{
    return [
        [
            'name' => 'HelpBnk Senior Laravel — 5+ years hard dealbreaker, cap 55',
            'skills' => [['requirement' => 'Laravel', 'weight' => 1.0, 'evidence' => 'in_bullet', 'position_ratio' => 0.1]],
            'relations' => ['laravel' => true],
            'similarities' => ['title_cosine' => 0.85, 'responsibility_cosine' => 0.8],
            'signals' => ['experience' => 60, 'location' => 90, 'education' => 80, 'language' => 70],
            'caps' => ['readable' => true, 'credential_ok' => false, 'evidence_ok' => true],
            // Credential cap holds it at 65 — below the 70 apply bar, which
            // is exactly why the candidate excluded it (dealbreaker).
            'expected_band' => ScoreBand::Consider,
        ],
        [
            'name' => 'Quik Hire Staffing — would have topped the table at 94',
            'skills' => [['requirement' => 'Laravel', 'weight' => 1.0, 'evidence' => 'in_bullet', 'position_ratio' => 0.1]],
            'relations' => ['laravel' => true],
            'similarities' => ['title_cosine' => 0.95, 'responsibility_cosine' => 0.95],
            'signals' => ['experience' => 100, 'location' => 95, 'education' => 90, 'language' => 90],
            'caps' => ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true],
            'expected_band' => ScoreBand::Strong,
        ],
        [
            'name' => 'CyberArrow Laravel — would have scored ~91',
            'skills' => [['requirement' => 'Laravel', 'weight' => 1.0, 'evidence' => 'in_bullet', 'position_ratio' => 0.2]],
            'relations' => ['laravel' => true],
            'similarities' => ['title_cosine' => 0.9, 'responsibility_cosine' => 0.9],
            'signals' => ['experience' => 95, 'location' => 90, 'education' => 85, 'language' => 85],
            'caps' => ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true],
            'expected_band' => ScoreBand::Strong,
        ],
        [
            'name' => 'Boldare/Proxify — skipped in the 60–69 band',
            'skills' => [['requirement' => 'Laravel', 'weight' => 1.0, 'evidence' => 'list_only', 'position_ratio' => 0.9]],
            'relations' => ['laravel' => true],
            'similarities' => ['title_cosine' => 0.6, 'responsibility_cosine' => 0.55],
            'signals' => ['experience' => 80, 'location' => 70, 'education' => 70, 'language' => 60],
            'caps' => ['readable' => true, 'credential_ok' => true, 'evidence_ok' => false],
            'expected_band' => ScoreBand::Consider,
        ],
    ];
}

it('agrees with the candidate judgement on the reconstructed held-out set (SC-3, T-093)', function (): void {
    $calculator = calibrationCalculator();
    $disagreements = [];

    foreach (noteCases() as $case) {
        $breakdown = $calculator->calculate(
            $case['skills'],
            new SkillRelationSet($case['relations']),
            $case['similarities'],
            $case['signals'],
            $case['caps'],
            calibrationRules(),
        );

        if ($breakdown->band !== $case['expected_band']) {
            $disagreements[] = "{$case['name']}: engine {$breakdown->band->value} vs judged {$case['expected_band']->value} (H={$breakdown->h}, S={$breakdown->s}, D={$breakdown->d}, raw={$breakdown->rawScore})";
        }
    }

    // Majority agreement, every disagreement explainable from the breakdown.
    expect($disagreements)->toBeEmpty();
});

it('shifts version-1 scores down only, never up (SC-4, T-094)', function (): void {
    $calculator = calibrationCalculator();
    $rows = [
        ['requirement' => 'Laravel', 'weight' => 1.0, 'evidence' => 'list_only', 'position_ratio' => 0.9],
        ['requirement' => 'Vue.js', 'weight' => 0.6, 'evidence' => 'in_bullet', 'position_ratio' => 0.8],
    ];
    $relations = new SkillRelationSet(['laravel' => true, 'vue.js' => true]);
    $similarities = ['title_cosine' => 0.75, 'responsibility_cosine' => 0.7];
    $signals = ['experience' => 90, 'location' => 85, 'education' => 80, 'language' => 75];
    $caps = ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true];

    // v1 = context and position factors at 1.0 (tested in ScoringMathTest).
    $v1 = (new HeuristicSkillScorer)->score($rows, $relations, 1.0, 1.0);
    $v2 = $calculator->calculate($rows, $relations, $similarities, $signals, $caps, calibrationRules());

    expect($v2->h)->toBeLessThanOrEqual($v1['h']);

    // A CV already at 100 on H keeps 100 under v2 (monotone upgrade).
    $perfect = [[
        'requirement' => 'Laravel', 'weight' => 1.0, 'evidence' => 'in_bullet', 'position_ratio' => 0.1,
    ]];
    $perfectBreakdown = $calculator->calculate(
        $perfect,
        new SkillRelationSet(['laravel' => true]),
        ['title_cosine' => 1.0, 'responsibility_cosine' => 1.0],
        ['experience' => 100, 'location' => 100, 'education' => 100, 'language' => 100],
        $caps,
        calibrationRules(),
    );

    expect($perfectBreakdown->h)->toBe(100.0);
});

it('fails the Hired Node/Nest template on the stack lock (G2)', function (): void {
    $verdicts = (new GateEvaluator)->evaluate(
        ['remote_scope' => 'remote_eu', 'title' => 'Node/Nest AI Trainer', 'text' => 'Node and NestJS role', 'url' => 'https://hired.com/jobs/1'],
        ['accepted_remote_scopes' => ['remote_eu'], 'stack_must' => ['Laravel'], 'stack_reject' => ['Node', 'NestJS']],
    );

    expect($verdicts[1]->passed)->toBeFalse()
        ->and($verdicts[1]->reasonCode)->toBe('stack_rejected');
});

it('describes fit-versus-priority order without predictive claims (T-147)', function (): void {
    // research §10 counts: 11 LinkedIn/aggregator silent; board + direct 2/5
    // (1 screening, 1 rejection, 3 silent). Descriptive only — n = 16 cannot
    // support predictive validity (research §17.2).
    $observations = [];

    for ($i = 0; $i < 11; $i++) {
        $observations[] = ['requirement' => 'Laravel', 'answered' => false];
    }

    $observations[] = ['requirement' => 'Laravel', 'answered' => true];
    $observations[] = ['requirement' => 'Vue.js', 'answered' => false];

    for ($i = 0; $i < 3; $i++) {
        $observations[] = ['requirement' => 'Vue.js', 'answered' => false];
    }

    $result = (new OutcomeCorrelator)->compare($observations, 8);

    expect($result['suppressed'])->toBeFalse()
        ->and($result['rows'])->not->toBeEmpty();
});
