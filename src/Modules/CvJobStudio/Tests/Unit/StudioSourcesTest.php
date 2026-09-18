<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;
use Modules\CvJobStudio\Domain\Services\NeverFetchHostPolicy;
use Modules\CvJobStudio\Domain\Services\OutboundUrlGuard;
use Modules\CvJobStudio\Domain\Services\RobotsTxtPolicy;
use Modules\CvJobStudio\Infrastructure\Sources\AdzunaSource;
use Modules\CvJobStudio\Infrastructure\Sources\GreenhouseBoardSource;
use Modules\CvJobStudio\Infrastructure\Sources\HimalayasSource;
use Modules\CvJobStudio\Infrastructure\Sources\ItJobsApiSource;
use Modules\CvJobStudio\Infrastructure\Sources\JobicySource;
use Modules\CvJobStudio\Infrastructure\Sources\RecruiteeBoardSource;
use Modules\CvJobStudio\Infrastructure\Sources\RemoteOkSource;
use Modules\CvJobStudio\Infrastructure\Sources\RemotiveSource;
use Modules\CvJobStudio\Infrastructure\Sources\TeamtailorSitemapSource;
use Modules\CvJobStudio\Infrastructure\Sources\WorkableBoardSource;

it('harvests Greenhouse-shaped ATS boards with full text (T-033 pattern)', function (): void {
    Http::fake([
        'boards-api.greenhouse.io/*' => Http::response([
            'jobs' => [
                [
                    'absolute_url' => 'https://boards.greenhouse.io/acme/jobs/1',
                    'title' => 'Senior Laravel Developer',
                    'location' => ['name' => 'Remote EU'],
                    'content' => '<p>Laravel role</p>',
                ],
            ],
        ], 200),
    ]);

    $source = new GreenhouseBoardSource(
        app(SpendGuardPort::class),
    );

    $result = $source->harvest('acme', 5);

    expect($result['postings'])->toHaveCount(1)
        ->and($result['postings'][0]['full_text'])->toBe('Laravel role')
        ->and($result['postings'][0]['discovery_channel'])->toBe('employer_ats');
});

it('treats ATS 404 as not-a-customer (T-009)', function (): void {
    Http::fake(['boards-api.greenhouse.io/*' => Http::response(null, 404)]);

    $source = new GreenhouseBoardSource(
        app(SpendGuardPort::class),
    );

    expect($source->harvest('not-a-customer', 5)['postings'])->toBe([]);
});

it('harvests Recruitee and Workable boards (T-036)', function (): void {
    Http::fake([
        '*.recruitee.com/*' => Http::response([
            'offers' => [[
                'title' => 'PHP Developer',
                'careers_url' => 'https://acme.recruitee.com/o/php-developer',
                'location' => 'Remote',
                'description' => '<p>PHP role</p>',
                'published_at' => '2026-09-10T00:00:00Z',
            ]],
        ], 200),
        'apply.workable.com/*' => Http::response([
            'jobs' => [[
                'title' => 'Vue Developer',
                'url' => 'https://acme.workable.com/jobs/1',
                'location' => 'Remote',
                'description' => 'Vue role',
                'published_on' => '2026-09-09',
            ]],
        ], 200),
    ]);

    $recruitee = (new RecruiteeBoardSource)->harvest('acme', 5);
    $workable = (new WorkableBoardSource)->harvest('acme', 5);

    expect($recruitee['postings'])->toHaveCount(1)
        ->and($recruitee['postings'][0]['posted_at_source'])->toBe('recruitee')
        ->and($workable['postings'])->toHaveCount(1)
        ->and($workable['postings'][0]['posted_at'])->toBe('2026-09-09');
});

it('walks a Teamtailor sitemap for job URLs only (T-005)', function (): void {
    Http::fake([
        '*.teamtailor.com/*' => Http::response(
            '<?xml version="1.0"?><urlset><url><loc>https://acme.teamtailor.com/jobs/123</loc><lastmod>2026-09-10</lastmod></url><url><loc>https://acme.teamtailor.com/about</loc></url></urlset>',
            200,
            ['Content-Type' => 'application/xml'],
        ),
    ]);

    $source = new TeamtailorSitemapSource(new OutboundUrlGuard, new RobotsTxtPolicy);

    $result = $source->harvest('acme', 5);

    expect($result['postings'])->toHaveCount(1)
        ->and($result['postings'][0]['url'])->toContain('/jobs/')
        ->and($result['postings'][0]['discovery_channel'])->toBe('employer_site');
});

it('harvests remote-board feeds with attribution intact (T-039)', function (): void {
    Http::fake([
        'remoteok.com/*' => Http::response([
            ['id' => 1, 'position' => 'Laravel Developer', 'company' => 'Acme', 'url' => 'https://remoteok.com/x', 'description' => 'Laravel remote role', 'date' => '2026-09-10T00:00:00'],
            ['legal' => 'RemoteOK footer'],
        ], 200),
        'remotive.com/*' => Http::response([
            'jobs' => [[
                'title' => 'Vue Developer', 'url' => 'https://remotive.com/x',
                'company_name' => 'Beta', 'description' => 'Vue remote role',
                'publication_date' => '2026-09-09T00:00:00',
            ]],
        ], 200),
        'himalayas.app/*' => Http::response([
            'jobs' => [[
                'title' => 'PHP Developer', 'url' => 'https://himalayas.app/x',
                'companyName' => 'Gamma', 'description' => 'PHP remote role',
                'publishedAt' => '2026-09-08T00:00:00Z',
            ]],
        ], 200),
        'jobicy.com/*' => Http::response([
            'jobs' => [[
                'jobTitle' => 'Laravel Developer', 'url' => 'https://jobicy.com/x',
                'companyName' => 'Delta', 'jobExcerpt' => 'excerpt',
                'jobDescription' => 'Laravel remote role', 'pubDate' => 'Tue, 09 Sep 2026 00:00:00 +0000',
            ]],
        ], 200),
    ]);

    expect((new RemoteOkSource)->harvest('laravel', 5)['postings'])->toHaveCount(1);
    expect((new RemotiveSource)->harvest('vue', 5)['postings'][0]['posted_at_source'])->toBe('remotive');
    expect((new HimalayasSource)->harvest('', 5)['postings'])->toHaveCount(1);
    expect((new JobicySource)->harvest('laravel', 5)['postings'][0]['employer'])->toBe('Delta');
});

it('makes no calls without credentials (T-040, T-114)', function (): void {
    Http::preventStrayRequests();

    config()->set('services.adzuna.app_id', '');
    config()->set('services.adzuna.api_key', '');
    config()->set('services.itjobs.api_key', '');

    expect((new AdzunaSource)->harvest('laravel', 5)['postings'])->toBe([])
        ->and((new ItJobsApiSource)->harvest('laravel', 5)['postings'])->toBe([]);
});

it('never fetches link_only hosts even as fallbacks (T-045)', function (): void {
    $policy = new NeverFetchHostPolicy;
    $guard = new OutboundUrlGuard(config('cv-job-studio.never_fetch_hosts', []));

    foreach (['linkedin.com', 'indeed.com', 'tecnoempleo.com', 'glassdoor.com'] as $host) {
        expect($policy->mayFetch('link_only'))->toBeFalse()
            ->and($guard->allowed("https://www.{$host}/jobs/1"))->toBeFalse();
    }
});
