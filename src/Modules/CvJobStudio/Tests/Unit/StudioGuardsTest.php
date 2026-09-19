<?php

declare(strict_types=1);

use Modules\CvJobStudio\Domain\Services\ApplyDestinationClassifier;
use Modules\CvJobStudio\Domain\Services\AtsStructureChecker;
use Modules\CvJobStudio\Domain\Services\BetaBinomialEstimator;
use Modules\CvJobStudio\Domain\Services\ExperimentMetricCalculator;
use Modules\CvJobStudio\Domain\Services\ExtractionQualityMeasurer;
use Modules\CvJobStudio\Domain\Services\FreshnessDecay;
use Modules\CvJobStudio\Domain\Services\GhostSignalDetector;
use Modules\CvJobStudio\Domain\Services\JdTextTrimmer;
use Modules\CvJobStudio\Domain\Services\NeverFetchHostPolicy;
use Modules\CvJobStudio\Domain\Services\OpportunityCalculator;
use Modules\CvJobStudio\Domain\Services\OpportunityPolicy;
use Modules\CvJobStudio\Domain\Services\PostingFingerprint;
use Modules\CvJobStudio\Domain\Services\PostingTextMinimiser;
use Modules\CvJobStudio\Domain\Services\RobotsTxtPolicy;
use Modules\CvJobStudio\Domain\Services\SeedStrategyBuilder;
use Modules\CvJobStudio\Domain\Services\TitleLevelClassifier;
use Modules\CvJobStudio\Domain\Services\TrigramSimilarity;
use Modules\CvJobStudio\Domain\Services\VocabularyRefreshTrigger;
use Modules\CvJobStudio\Infrastructure\Ai\FailoverPolicy;
use Modules\CvJobStudio\Infrastructure\Fetching\OutboundUrlGuard;

it('blocks link_only and resolve_only modes from fetching', function (): void {
    $policy = new NeverFetchHostPolicy;

    expect($policy->mayFetch('link_only'))->toBeFalse()
        ->and($policy->mayFetch('resolve_only'))->toBeFalse()
        ->and($policy->mayFetch('api_feed'))->toBeTrue()
        ->and($policy->mayFetch('search_scoped'))->toBeTrue();
});

it('denies private ranges, metadata IP and never-fetch hosts', function (): void {
    $guard = new OutboundUrlGuard(['linkedin.com', 'indeed.com']);

    expect($guard->allowed('https://boards.greenhouse.io/acme/jobs/1'))->toBeTrue()
        ->and($guard->allowed('http://127.0.0.1/jobs'))->toBeFalse()
        ->and($guard->allowed('http://10.0.0.5/x'))->toBeFalse()
        ->and($guard->allowed('http://169.254.169.254/latest'))->toBeFalse()
        ->and($guard->allowed('https://www.linkedin.com/jobs/1'))->toBeFalse()
        ->and($guard->allowed('https://www.indeed.com/viewjob?jk=1'))->toBeFalse()
        ->and($guard->allowed('ftp://files.example.com/jobs'))->toBeFalse()
        ->and($guard->allowed('not a url'))->toBeFalse();
});

it('refuses robots-disallowed paths before any request', function (): void {
    $policy = new RobotsTxtPolicy(['tecnoempleo.com' => ['/alertas-empleo-rss.php']]);

    expect($policy->mayFetch('https://tecnoempleo.com/alertas-empleo-rss.php'))->toBeFalse()
        ->and($policy->mayFetch('https://tecnoempleo.com/ofertas'))->toBeTrue();
});

it('collides the same job across boards but not across weeks', function (): void {
    $a = PostingFingerprint::make('Möller GmbH', 'Senior Laravel Developer', 'Remote EU', '2026-09-14');
    $b = PostingFingerprint::make('Moller Gmbh', 'Senior Laravel Developer', 'Remote EU', '2026-09-14');
    $c = PostingFingerprint::make('Möller GmbH', 'Senior Laravel Developer', 'Remote EU', '2026-09-21');

    expect($a)->toBe($b)->and($a)->not->toBe($c);
});

it('matches similar titles and refuses ambiguity', function (): void {
    $similarity = new TrigramSimilarity;

    expect($similarity->similarity('Senior Laravel Developer', 'Senior Laravel Developer'))->toBe(1.0)
        ->and($similarity->similarity('Senior Laravel Developer', 'Senior Laravel Entwickler'))->toBeGreaterThan(0.55)
        ->and($similarity->similarity('Senior Laravel Developer', 'Junior Accountant'))->toBeLessThan(0.3);
});

it('builds keyword strategies by access mode', function (): void {
    $seeds = (new SeedStrategyBuilder)->build([
        'keywords' => ['laravel', 'vue'],
        'portals' => [
            ['name' => 'greenhouse', 'access_mode' => 'api_feed', 'domains' => []],
            ['name' => 'tecnoempleo', 'access_mode' => 'link_only', 'domains' => ['tecnoempleo.com']],
        ],
    ]);

    expect($seeds[0]['mode'])->toBe('harvest_whole')
        ->and($seeds[1]['mode'])->toBe('search_scoped')
        ->and($seeds[1]['include_domains'])->toBe(['tecnoempleo.com']);
});

it('classifies apply destinations and detects ATS kinds', function (): void {
    $classifier = new ApplyDestinationClassifier;

    expect($classifier->classify('https://boards.greenhouse.io/acme/jobs/1'))->toBe('at_source')
        ->and($classifier->atsKind('https://jobs.lever.co/acme/abc'))->toBe('lever')
        ->and($classifier->classify('https://www.linkedin.com/jobs/view/1'))->toBe('social');
});

it('decays freshness and penalises unknown age', function (): void {
    $decay = new FreshnessDecay;

    expect($decay->factor(0.0, 14.0, 0.5, 0.9))->toBe(1.0)
        ->and($decay->factor(14.0, 14.0, 0.5, 0.9))->toBe(0.5)
        ->and($decay->factor(null, 14.0, 0.5, 0.9))->toBe(0.9)
        ->and($decay->factor(140.0, 14.0, 0.5, 0.9))->toBe(0.5);
});

it('detects ghost signals in three languages', function (): void {
    $detector = new GhostSignalDetector;

    $signals = $detector->signals([
        'posted_at' => null, 'age_days' => 45.0, 'relist_count' => 4,
        'text' => 'Únete a nuestra bolsa de talento. We are always looking.',
        'channel' => 'aggregator',
    ]);

    expect($signals)->toContain('no_date')
        ->and($signals)->toContain('stale_over_30d')
        ->and($signals)->toContain('relisted')
        ->and($signals)->toContain('evergreen_wording')
        ->and($signals)->toContain('intermediary_only');
});

it('reads title seniority in EN/ES/PT', function (): void {
    $classifier = new TitleLevelClassifier;

    expect($classifier->stepOf('Senior Laravel Developer'))->toBe(2)
        ->and($classifier->stepOf('Desarrollador Júnior'))->toBe(0)
        ->and($classifier->stepOf('Tech Lead Vue'))->toBe(3)
        ->and($classifier->stepOf('PHP Developer'))->toBeNull();
});

it('holds the policy below the evidence gate (SC-14 logic)', function (): void {
    $estimator = new BetaBinomialEstimator;

    // The real seniority data: 0/7 vs 1/9 — the factor must stay at policy.
    $junior = $estimator->estimate(0.9, 0, 7, 30, 3, 100.0);
    $senior = $estimator->estimate(0.7, 1, 9, 30, 3, 100.0);

    expect($junior['applied'])->toBeFalse()->and($junior['value'])->toBe(0.9)
        ->and($senior['applied'])->toBeFalse()->and($senior['value'])->toBe(0.7);
});

it('validates the opportunity policy and neutralises exactly (SC-13)', function (): void {
    $policy = OpportunityPolicy::fromConfig(config('cv-job-studio.opportunity'));

    expect($policy->channel('employer_site')['value'])->toBe(1.0);

    $neutral = OpportunityPolicy::fromConfig(config('cv-job-studio.opportunity'), true);

    $calculator = new OpportunityCalculator(new FreshnessDecay, new GhostSignalDetector, new TitleLevelClassifier);

    $result = $calculator->calculate([
        'channel' => 'aggregator', 'age_days' => 60.0, 'posted_at' => '2026-07-01',
        'relist_count' => 5, 'text' => 'always hiring', 'title' => 'Senior Developer',
        'profile_min_step' => 1, 'profile_max_step' => 2,
    ], $neutral);

    expect($result['factor'])->toBe(1.0)->and($result['components'])->toBe([]);

    $real = $calculator->calculate([
        'channel' => 'aggregator', 'age_days' => 60.0, 'posted_at' => '2026-07-01',
        'relist_count' => 5, 'text' => 'always hiring', 'title' => 'Senior Developer',
        'profile_min_step' => 1, 'profile_max_step' => 2,
    ], $policy);

    expect($real['factor'])->toBeLessThan(1.0)
        ->and($real['components'])->toHaveKeys(['channel', 'freshness', 'ghost', 'seniority']);
});

it('rejects out-of-range policy factors without grade/source', function (): void {
    try {
        $policy = OpportunityPolicy::fromConfig(['channel' => ['x' => ['value' => 2.0, 'grade' => 'C', 'source' => 's']]]);

        expect($policy)->toBeNull('Expected InvalidArgumentException');
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->toContain('out of [0.1, 1]');
    }
});

it('strips boilerplate but keeps requirement lines', function (): void {
    $trimmed = (new JdTextTrimmer)->trim(
        "Senior Laravel Developer\n\nYou will build APIs with Laravel and Vue.\n\nBenefits:\nFree coffee and gym.\n\nEqual opportunity employer statement here.",
        5000,
    );

    expect($trimmed)->toContain('build APIs')
        ->and($trimmed)->not->toContain('Free coffee');
});

it('minimises personal data but keeps the application channel', function (): void {
    $minimiser = new PostingTextMinimiser;

    $clean = $minimiser->minimise(
        "Contact Maria García, Talent Partner, at maria@agency.com or +34 600 111 222.\nApply at jobs@acme.com.\nWe need Laravel experience.",
        'jobs@acme.com',
    );

    expect($clean)->not->toContain('Maria García')
        ->and($clean)->not->toContain('maria@agency.com')
        ->and($clean)->not->toContain('+34 600 111 222')
        ->and($clean)->toContain('jobs@acme.com')
        ->and($clean)->toContain('Laravel experience');
});

it('asserts ATS structure on snapshots', function (): void {
    $checker = new AtsStructureChecker;

    $good = $checker->check([
        'sections' => [['heading' => 'Experience'], ['heading' => 'Skills']],
        'font' => 'Calibri', 'font_size_pt' => 10.0, 'page_count' => 2,
        'has_tables' => false, 'has_images' => false, 'has_columns' => false,
        'contact_in_body' => true,
        'dates' => ['01/2022', '06/2024'],
    ]);

    $bad = $checker->check([
        'sections' => [['heading' => 'My Journey']],
        'font' => 'Comic Sans', 'font_size_pt' => 14.0, 'page_count' => 4,
        'has_tables' => true, 'has_images' => true, 'has_columns' => true,
        'contact_in_body' => false,
        'dates' => ['Jan 2022'],
    ]);

    expect($good['passed'])->toBeTrue()->and($bad['passed'])->toBeFalse();
});

it('fails over on connection errors but never on schema errors', function (): void {
    $policy = new FailoverPolicy;

    expect($policy->isRetryable(new RuntimeException('connection refused')))->toBeTrue()
        ->and($policy->isRetryable(new RuntimeException('429 too many requests')))->toBeTrue()
        ->and($policy->isRetryable(new RuntimeException('schema validation failed: invalid output')))->toBeFalse()
        ->and($policy->isRetryable(new RuntimeException('401 unauthorized')))->toBeFalse();
});

it('measures extraction quality against a labelled fixture (T-095 harness)', function (): void {
    $result = (new ExtractionQualityMeasurer)->measure(
        ['Laravel', 'Vue.js', 'PostgreSQL'],
        ['Laravel', 'Vue.js', 'Kubernetes'],
    );

    expect($result['precision'])->toBeGreaterThan(0.66)->toBeLessThan(0.67)
        ->and($result['recall'])->toBeGreaterThan(0.66)->toBeLessThan(0.67)
        ->and($result['hallucinated'])->toBe(['kubernetes'])
        ->and($result['missed'])->toBe(['postgresql']);
});

it('computes yield per euro per experiment cell (T-030)', function (): void {
    $metric = (new ExperimentMetricCalculator)->metric([
        'candidates' => 100, 'gate_pass_rate' => 0.4, 'unique_after_dedup' => 30,
        'scored_gte_70' => 10, 'cost_micros' => 2_000_000,
    ]);

    expect($metric['yield_per_euro'])->toEqual(15)
        ->and($metric['relevant_share'])->toBe(0.1);
});

it('evaluates vocabulary refresh triggers as data (T-028)', function (): void {
    $trigger = new VocabularyRefreshTrigger;

    expect($trigger->evaluate(['terms_count' => 0, 'refreshed_at' => null, 'last_yield' => null], false)['trigger'])->toBe('cache-miss')
        ->and($trigger->evaluate(['terms_count' => 5, 'refreshed_at' => '2026-01-01', 'last_yield' => 0.5], false)['trigger'])->toBe('stale-week')
        ->and($trigger->evaluate(['terms_count' => 5, 'refreshed_at' => date('Y-m-d'), 'last_yield' => 0.01], false)['trigger'])->toBe('low-yield')
        ->and($trigger->evaluate(['terms_count' => 5, 'refreshed_at' => date('Y-m-d'), 'last_yield' => 0.5], false)['trigger'])->toBe('none')
        ->and($trigger->evaluate(['terms_count' => 5, 'refreshed_at' => date('Y-m-d'), 'last_yield' => 0.5], true)['trigger'])->toBe('forced');
});
