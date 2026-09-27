<?php

declare(strict_types=1);

use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutJobPostingFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Modules\LeadScout\Application\Commands\DiscoverAgenciesHandler;
use Modules\LeadScout\Application\Commands\EnrichCompanyHandler;
use Modules\LeadScout\Application\Commands\ResolveCompanyHandler;
use Modules\LeadScout\Domain\Enums\CompanyOrigin;
use Modules\LeadScout\Domain\Services\CompanyCountryResolver;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutContactChannelEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSignalEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;
use Modules\LeadScout\Infrastructure\Queue\EnrichCompanyJob;
use Modules\LeadScout\Infrastructure\Queue\ExtractSignalsJob;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();

    config()->set('services.tavily.api_key', 'test-key');
    config()->set('services.tavily.url', 'https://api.tavily.com/search');
    config()->set('services.tavily.max_results', 5);
});

function tavilyResults(array $urls): array
{
    return ['results' => array_map(
        static fn (string $url): array => ['title' => 'Agencia '.pitchers($url), 'url' => $url, 'content' => 'Snippet', 'score' => 0.9],
        $urls,
    )];
}

function pitchers(string $url): string
{
    return (string) preg_replace('~^https?://(www\.)?~', '', $url);
}

it('discovers agencies while discarding portals and networks', function (): void {
    Http::fake(['https://api.tavily.com/search' => Http::response(tavilyResults([
        'https://novita-labs.example/servicios',
        'https://sortlist.com/agency/novita',
        // 2026-09-25: these six reached the database as leads because the
        // denylist only knew the .com/primary hosts of each marketplace.
        'https://sortlist.es/agencias/laravel',
        'https://workana.com/freelancer/laravel',
        'https://zaask.pt/trabalhar/freelancer',
        'https://facebook.com/laraveljobs',
        'https://www.linkedin.com/company/novita',
        'not-a-url',
    ]), 200)]);

    $report = app(DiscoverAgenciesHandler::class)->handle('wave1', 'ES', 'agencia desarrollo Laravel');

    expect($report['new_companies'])->toBe(1)
        ->and($report['skipped'])->toBe(7);

    $company = ScoutCompanyEloquentModel::query()->where('canonical_domain', 'novita-labs.example')->firstOrFail();

    // FR-38: the display name is the domain, never the search result
    // <title> (usually a post title, not the company identity).
    expect($company->name)->toBe('novita-labs.example');

    expect($company->origin->value)->toBe(CompanyOrigin::Discovery->value)
        ->and($company->discovery_wave)->toBe('wave1')
        ->and($company->origin_ref)->toContain('agencia desarrollo Laravel')
        ->and($company->timezone_overlap_hours)->toBe(8);

    // Not one marketplace, directory or social row created.
    expect(ScoutCompanyEloquentModel::query()
        ->whereIn('canonical_domain', ['sortlist.es', 'workana.com', 'zaask.pt', 'facebook.com', 'sortlist.com', 'linkedin.com'])
        ->count())->toBe(0);

    Queue::assertPushed(EnrichCompanyJob::class);
});

it('never duplicates, re-enriches or bills existing and suppressed domains', function (): void {
    $existing = ScoutCompanyFactory::new()->create(['canonical_domain' => 'novita-labs.example']);
    ScoutSuppressionEloquentModel::query()->create([
        'canonical_domain' => 'vetada.example', 'source' => 'manual', 'reason' => 'DNC',
    ]);

    Http::fake(['https://api.tavily.com/search' => Http::response(tavilyResults([
        'https://novita-labs.example/',
        'https://vetada.example/',
    ]), 200)]);

    $report = app(DiscoverAgenciesHandler::class)->handle('wave1', 'ES', 'agencia desarrollo Laravel');

    expect($report['new_companies'])->toBe(0)
        ->and(ScoutCompanyEloquentModel::query()->where('canonical_domain', 'novita-labs.example')->count())->toBe(1);

    Queue::assertNotPushed(EnrichCompanyJob::class);
    Http::assertNotSent(fn (Request $request): bool => ! str_contains($request->url(), 'api.tavily.com'));
});

it('splits queries across waves by weight', function (): void {
    $planned = app(DiscoverAgenciesHandler::class)->planQueries(null, null, null);
    $waves = array_count_values(array_column($planned, 'wave'));

    expect($planned)->toHaveCount(16)
        ->and($waves['wave1'])->toBe(9)
        ->and($waves['wave2'])->toBe(5)
        ->and($waves['wave3'])->toBe(2);
});

it('resolves postings from the payload, then search, then gives up unpaid', function (): void {
    Http::fake([
        'https://api.tavily.com/search' => Http::sequence(
            [tavilyResults(['https://orbita.example/'])],
            [['results' => []]],
        )->whenEmpty(Http::response(['results' => []], 200)),
    ]);

    $withUrl = ScoutJobPostingFactory::new()->unresolved()->create([
        'company_name' => 'Nebula Labs',
        'company_url' => 'https://www.nebula-labs.pt',
    ]);

    $company = app(ResolveCompanyHandler::class)->handle($withUrl->uuid);

    expect($company)->not->toBeNull()
        ->and($company->canonicalDomain)->toBe('nebula-labs.pt')
        ->and($withUrl->refresh()->company_id)->toBe($company->id);

    $searchable = ScoutJobPostingFactory::new()->unresolved()->create(['company_name' => 'Orbita Studio']);
    $resolved = app(ResolveCompanyHandler::class)->handle($searchable->uuid);

    expect($resolved?->canonicalDomain)->toBe('orbita.example');

    $hopeless = ScoutJobPostingFactory::new()->unresolved()->create(['company_name' => 'Ghost Studio']);
    expect(app(ResolveCompanyHandler::class)->handle($hopeless->uuid))->toBeNull()
        ->and($hopeless->refresh()->company_id)->toBeNull();
});

it('enriches pages, channels and public data, then chains scoring', function (): void {
    $html = fn (string $body): string => "<html><body>{$body}</body></html>";

    Http::fake([
        'https://agencia.example/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://agencia.example/sitemap.xml' => Http::response(
            '<?xml version="1.0"?><urlset><url><loc>https://agencia.example/servicios</loc><lastmod>2026-08-01</lastmod></url></urlset>',
            200, ['Content-Type' => 'application/xml'],
        ),
        'https://agencia.example/' => Http::response($html(
            '<h1>Agencia Ejemplo S.L.</h1><a href="https://agencia.example/servicios">Servicios</a> '
            .'<a href="https://agencia.example/contacto">Contacto</a>'
        ), 200, ['Content-Type' => 'text/html']),
        'https://agencia.example/servicios' => Http::response($html(
            '<h2>Desarrollamos con Laravel y Vue.</h2><p>Somos 12 personas. Caso de éxito del 12 de mayo de 2026.</p>'
        ), 200, ['Content-Type' => 'text/html']),
        'https://agencia.example/contacto' => Http::response($html(
            '<p>Escríbenos a info@agencia.example. Trabajamos con freelancers.</p>'
        ), 200, ['Content-Type' => 'text/html']),
    ]);

    $company = ScoutCompanyFactory::new()->create(['canonical_domain' => 'agencia.example', 'country' => 'ES']);

    $report = app(EnrichCompanyHandler::class)->handle($company->uuid);

    expect($report['status'])->toBe('enriched')
        ->and($report['pages'])->toBeGreaterThanOrEqual(3)
        ->and($company->refresh()->team_size_observed)->toBeNull();

    expect(ScoutContactChannelEloquentModel::query()
        ->where('company_id', $company->id)->count())->toBeGreaterThanOrEqual(2);

    Queue::assertPushed(ExtractSignalsJob::class, fn (ExtractSignalsJob $job): bool => $job->companyUuid === $company->uuid);
});

it('resolves the company name from JSON-LD, never from a page title', function (): void {
    $html = fn (string $body): string => "<html><head><title>10 trucos de Laravel que debes conocer</title></head><body>{$body}</body></html>";

    Http::fake([
        'https://legalname.example/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://legalname.example/sitemap.xml' => Http::response('oops', 404),
        'https://legalname.example/' => Http::response($html(
            '<script type="application/ld+json">{"@type":"Organization","legalName":"Legalname Consulting S.L.","taxID":"B87654321","address":{"addressLocality":"Valencia"}}</script>'
            .'<h1>10 trucos de Laravel que debes conocer</h1><a href="https://legalname.example/servicios">Servicios</a>'
        ), 200, ['Content-Type' => 'text/html']),
        'https://legalname.example/servicios' => Http::response($html(
            '<h2>Desarrollamos con Laravel.</h2><p>Somos 8 personas.</p>'
        ), 200, ['Content-Type' => 'text/html']),
    ]);

    // Discovery seeds the domain placeholder (FR-38: never the <title>).
    $company = ScoutCompanyFactory::new()->discovered()->create([
        'canonical_domain' => 'legalname.example',
        'name' => 'legalname.example',
        'country' => 'ES',
    ]);

    $report = app(EnrichCompanyHandler::class)->handle($company->uuid);

    expect($report['status'])->toBe('enriched')
        ->and($company->refresh()->legal_name)->toBe('Legalname Consulting S.L.')
        ->and($company->refresh()->name)->toBe('Legalname Consulting S.L.');
});

it('carries the JSON-LD country through with its source and evidence', function (): void {
    // The wiring, not the resolver: the country is read from the extractor
    // output, which is filtered by the public-field allowlist before anything
    // else is stored. `country` is deliberately NOT on that allowlist — it is
    // the conclusion of the resolution, not public data — so the wiring has to
    // pass the unfiltered array or the value silently disappears and the
    // search-derived country stands unchallenged.
    $html = fn (string $body): string => "<html><body>{$body}</body></html>";

    Http::fake([
        'https://warsawdev.example/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://warsawdev.example/sitemap.xml' => Http::response('oops', 404),
        'https://warsawdev.example/' => Http::response($html(
            '<script type="application/ld+json">{"@type":"Organization","legalName":"Warsaw Dev Sp. z o.o.","address":{"addressCountry":"PL"}}</script>'
            .'<a href="https://warsawdev.example/servicios">Servicios</a>'
        ), 200, ['Content-Type' => 'text/html']),
        'https://warsawdev.example/servicios' => Http::response($html(
            '<h2>Developers for Laravel.</h2><p>We are 20 people.</p>'
        ), 200, ['Content-Type' => 'text/html']),
    ]);

    // Discovery guessed PT from the query that surfaced the company.
    $company = ScoutCompanyFactory::new()->discovered()->create([
        'canonical_domain' => 'warsawdev.example',
        'name' => 'warsawdev.example',
        'country' => 'PT',
    ]);

    app(EnrichCompanyHandler::class)->handle($company->uuid);

    $company->refresh();

    expect($company->country)->toBe('PL')
        ->and($company->country_source)->toBe(CompanyCountryResolver::SOURCE_JSONLD)
        ->and($company->country_evidence_url)->toBe('https://warsawdev.example/')
        // The search-derived guess is kept, so an auditor can see the two
        // disagreed rather than having to trust that they did.
        ->and($company->country_origin)->toBe('PT');
});

it('discards natural persons without storing identification', function (): void {
    $html = fn (string $body): string => "<html><body>{$body}</body></html>";

    Http::fake([
        'https://juan.example/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://juan.example/sitemap.xml' => Http::response('oops', 404),
        'https://juan.example/' => Http::response($html(
            '<h1>Juan Pérez, autónomo</h1><p>Soy desarrollador Laravel freelance. NIF 12345678Z.</p>'
        ), 200, ['Content-Type' => 'text/html']),
    ]);

    $company = ScoutCompanyFactory::new()->create(['canonical_domain' => 'juan.example', 'country' => 'ES']);

    $report = app(EnrichCompanyHandler::class)->handle($company->uuid);

    expect($report['status'])->toBe('solo_freelancer')
        ->and($company->refresh()->legal_name)->toBeNull()
        ->and($company->refresh()->tax_id)->toBeNull()
        ->and(ScoutSignalEloquentModel::query()
            ->where('company_id', $company->id)->where('signal_key', 'solo_freelancer')->exists())->toBeTrue();
});
