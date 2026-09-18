<?php

declare(strict_types=1);

use Database\Factories\ScoutJobPostingFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\LeadScout\Application\Commands\ExpirePostingsHandler;
use Modules\LeadScout\Application\Commands\IngestSourceHandler;
use Modules\LeadScout\Domain\Enums\PostingStatus;
use Modules\LeadScout\Domain\Enums\SourceStatus;
use Modules\LeadScout\Domain\Enums\SourceType;
use Modules\LeadScout\Domain\Ports\CompanyRepositoryPort;
use Modules\LeadScout\Domain\Ports\JobPostingRepositoryPort;
use Modules\LeadScout\Domain\Services\SuppressionGate;
use Modules\LeadScout\Infrastructure\JobSources\ArbeitnowApiSource;
use Modules\LeadScout\Infrastructure\JobSources\RssFeedSource;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutJobPostingEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    config()->set('lead-scout.job_sources.rss_feeds', [
        'LaraJobs' => 'https://feed.test/larajobs.xml',
        'Remotive' => 'https://feed.test/remotive.xml',
    ]);
});

function rssSource(string $name = 'LaraJobs'): ScoutSourceEloquentModel
{
    return ScoutSourceEloquentModel::query()->create([
        'name' => $name,
        'type' => SourceType::Rss->value,
        'access_method' => 'rss',
        'frequency_minutes' => 360,
        'priority' => 5,
        'status' => SourceStatus::Active->value,
        'terms_reviewed_at' => now(),
    ]);
}

function ingestHandler(): IngestSourceHandler
{
    return new IngestSourceHandler(
        app(CompanyRepositoryPort::class),
        app(JobPostingRepositoryPort::class),
        app(SuppressionGate::class),
        app(CircuitBreakerInterface::class),
        [new RssFeedSource, new ArbeitnowApiSource],
    );
}

function rssXml(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/'.$name);
}

it('parses rss items with company fallback from the title', function (): void {
    $items = iterator_to_array(RssFeedSource::parseItems(rssXml('rss-larajobs.xml')));

    expect($items)->toHaveCount(2)
        ->and($items[0]->companyName)->toBe('Nebula Labs')
        ->and($items[0]->sourceUrl)->toBe('https://larajobs.example.com/jobs/1')
        ->and($items[1]->companyName)->toBe('Orbita Studio');
});

it('dedupes the same offer across two sources with both pivots', function (): void {
    Http::fake([
        'https://feed.test/larajobs.xml' => Http::response(rssXml('rss-larajobs.xml'), 200),
        'https://feed.test/remotive.xml' => Http::response(rssXml('rss-larajobs.xml'), 200),
    ]);

    $a = rssSource('LaraJobs');
    $b = rssSource('Remotive');

    ingestHandler()->handle($a);
    $counts = ingestHandler()->handle($b);

    expect(ScoutJobPostingEloquentModel::query()->count())->toBe(2)
        ->and($counts['linked'])->toBe(2)
        ->and(DB::table('scout_job_posting_sources')->count())->toBe(4);
});

it('keeps ingesting other sources when one feed fails', function (): void {
    Http::fake([
        'https://feed.test/larajobs.xml' => Http::response(rssXml('rss-larajobs.xml'), 200),
        'https://feed.test/remotive.xml' => Http::response('boom', 500),
    ]);

    $ok = rssSource('LaraJobs');
    $down = rssSource('Remotive');

    expect(ingestHandler()->handle($ok)['created'])->toBe(2);

    try {
        ingestHandler()->handle($down);
        $this->fail('Expected the failing source to throw.');
    } catch (Throwable) {
        expect($down->refresh()->status->value)->toBe(SourceStatus::Failing->value);
    }

    expect(ScoutJobPostingEloquentModel::query()->count())->toBe(2);
});

it('discards irrelevant offers and marks expired ones without a company', function (): void {
    Http::fake([
        'https://feed.test/larajobs.xml' => Http::response(
            '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title>'
            .'<item><title>Senior Accountant (on-site)</title><link>https://feed.test/jobs/9</link>'
            .'<pubDate>Mon, 01 Jun 2026 09:00:00 +0000</pubDate>'
            .'<description>Accounting role, no tech stack.</description></item>'
            .'</channel></rss>',
            200,
        ),
    ]);

    $counts = ingestHandler()->handle(rssSource());

    expect($counts['irrelevant'])->toBe(1)
        ->and(ScoutJobPostingEloquentModel::query()->count())->toBe(0);
});

it('skips suppressed companies without enrichment', function (): void {
    Http::fake([
        'https://feed.test/remotive.xml' => Http::response(rssXml('rss-remotive.xml'), 200),
    ]);

    ScoutSuppressionEloquentModel::query()->create([
        'name' => 'Remota Corp',
        'source' => 'manual',
        'reason' => 'Asked not to be contacted',
    ]);

    $counts = ingestHandler()->handle(rssSource('Remotive'));

    expect($counts['suppressed'])->toBe(1)
        ->and(ScoutJobPostingEloquentModel::query()->count())->toBe(0);
});

it('expires postings past the max age without generating leads', function (): void {
    $posting = ScoutJobPostingFactory::new()->create([
        'published_at' => now()->subDays(60),
        'status' => PostingStatus::Active,
    ]);

    $expired = app(ExpirePostingsHandler::class)->handle();

    expect($expired)->toBe(1)
        ->and($posting->refresh()->status)->toBe(PostingStatus::Expired);
});

it('parses arbeitnow jobs with pagination', function (): void {
    $page1 = (string) file_get_contents(__DIR__.'/../Fixtures/arbeitnow-page1.json');

    Http::fake(['*' => Http::response(json_decode($page1, true), 200)]);

    $source = ScoutSourceEloquentModel::query()->create([
        'name' => 'Arbeitnow',
        'type' => SourceType::JobApi->value,
        'access_method' => 'api',
        'frequency_minutes' => 360,
        'priority' => 10,
        'status' => SourceStatus::Active->value,
        'terms_reviewed_at' => now(),
    ]);

    $counts = ingestHandler()->handle($source);

    expect($counts['created'])->toBe(2)
        ->and(ScoutJobPostingEloquentModel::query()->where('title', 'PHP Freelancer')->exists())->toBeTrue();
});
