<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\LeadScoutAiSettingsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\LeadScout\Application\Commands\DiscoverAgenciesHandler;
use Modules\LeadScout\Infrastructure\Ai\ExtractCompanySignalsAgent;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutScoreResultEloquentModel;
use Modules\LeadScout\Tests\Support\RecordingAiClient;
use Shared\Infrastructure\AI\AIClientInterface;

// Full pipeline on the sync queue driver (phpunit.xml QUEUE_CONNECTION=sync):
// discover → enrich → extract → score, with fakes at every boundary and
// the AI spy proving the exfiltration rules (T059).
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(LeadScoutAiSettingsSeeder::class);

    config()->set('services.tavily.api_key', 'test-key');
    config()->set('services.tavily.url', 'https://api.tavily.com/search');
    config()->set('services.tavily.max_results', 5);
    config()->set('services.firecrawl.api_key', 'test-key');
    config()->set('ai.providers.gemini.key', 'test-gemini-key');
});

function chainHtml(string $body): string
{
    return "<html><body>{$body}</body></html>";
}

it('runs ingest, enrich, extract and score end to end', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    RecordingAiClient::install([ExtractCompanySignalsAgent::class => [
        'signals' => [[
            'signal_key' => 'accepts_external',
            'nature' => 'fact',
            'excerpt' => 'Trabajamos con freelancers.',
            'confidence' => 85,
            'source_url' => 'https://cadena.example/contacto',
        ]],
        'company_type' => 'software_agency',
        'team_size_observed' => 14,
    ]]);

    Http::fake([
        'https://api.tavily.com/search' => Http::response(['results' => [[
            'title' => 'Cadena Labs', 'url' => 'https://cadena.example/', 'content' => 'Agencia Laravel.',
            'score' => 0.9,
        ]]], 200),
        'https://cadena.example/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://cadena.example/sitemap.xml' => Http::response('empty', 404),
        'https://cadena.example/' => Http::response(chainHtml(
            '<h1>Cadena Labs</h1>'
            .'<a href="https://cadena.example/servicios">Servicios</a> '
            .'<a href="https://cadena.example/equipo">Equipo</a> '
            .'<a href="https://cadena.example/contacto">Contacto</a>',
        ), 200, ['Content-Type' => 'text/html']),
        'https://cadena.example/servicios' => Http::response(chainHtml(
            '<h2>Desarrollamos con Laravel y Vue. Somos 14 personas.</h2>'
            .'<p>Caso de éxito del 12 de mayo de 2026. © 2026.</p>',
        ), 200, ['Content-Type' => 'text/html']),
        'https://cadena.example/equipo' => Http::response(chainHtml(
            '<p>**Ana Ruiz** — CEO</p><p>**Pedro Gil** — Recruiter</p>',
        ), 200, ['Content-Type' => 'text/html']),
        'https://cadena.example/contacto' => Http::response(chainHtml(
            '<p>Trabajamos con freelancers. Escríbenos a info@cadena.example.</p>',
        ), 200, ['Content-Type' => 'text/html']),
    ]);

    $report = app(DiscoverAgenciesHandler::class)->handle('wave1', 'ES', null);

    expect($report['new_companies'])->toBe(1);

    // The sync queue ran enrich → extract → score inline.
    $company = ScoutCompanyEloquentModel::query()->where('canonical_domain', 'cadena.example')->firstOrFail();
    $result = ScoutScoreResultEloquentModel::query()
        ->where('company_id', $company->id)->where('is_current', true)->firstOrFail();

    expect($result->lead_score)->toBeGreaterThan(0)
        ->and($result->reasons()->count())->toBeGreaterThan(5)
        ->and($company->refresh()->team_size_observed)->toBe(14);

    // No HTTP to forbidden hosts, no AI call without evidence.
    Http::assertNotSent(fn (Request $request): bool => ! str_contains($request->url(), 'cadena.example')
        && ! str_contains($request->url(), 'api.tavily.com'));

    $ai = app(AIClientInterface::class);

    expect($ai)->toBeInstanceOf(RecordingAiClient::class)
        ->and(count($ai->calls))->toBe(1)
        ->and($ai->calls[0]['prompt'])->not->toContain('Ana Ruiz', 'Pedro Gil');
});
