<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\ScoutCompanyFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Enums\FetchStatus;
use Modules\LeadScout\Infrastructure\Budgets\BudgetLedger;
use Modules\LeadScout\Infrastructure\Fetching\DirectHttpPageFetcher;
use Modules\LeadScout\Infrastructure\Fetching\FetchLadder;
use Modules\LeadScout\Infrastructure\Fetching\FirecrawlPageFetcher;
use Modules\LeadScout\Infrastructure\Fetching\FormSummaryExtractor;
use Modules\LeadScout\Infrastructure\Fetching\OutboundUrlGuard;
use Modules\LeadScout\Infrastructure\Fetching\RobotsTxtPolicy;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutFetchAttemptEloquentModel;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;

uses(RefreshDatabase::class);

function fetchCompany(): ScoutCompanyEloquentModel
{
    return ScoutCompanyFactory::new()->create();
}

function ladder(): FetchLadder
{
    return new FetchLadder(
        new RobotsTxtPolicy,
        new OutboundUrlGuard(static fn (string $host): array => ['93.184.216.34']),
        new DirectHttpPageFetcher(new OutboundUrlGuard(static fn (string $host): array => ['93.184.216.34'])),
        new FirecrawlPageFetcher(
            new OutboundUrlGuard(static fn (string $host): array => ['93.184.216.34']),
            app(CircuitBreakerInterface::class),
        ),
        new BudgetLedger,
    );
}

it('summarizes forms and discards the html', function (): void {
    $summary = FormSummaryExtractor::summarize(
        '<html><body><form action="/contacto" method="post">'
        .'<input type="text" name="nombre"><input type="email" name="email">'
        .'<textarea name="mensaje"></textarea></form>'
        .'<form action="https://other.example/send"><input type="text" name="q"></form>'
        .'</body></html>',
        'https://agencia.example/contacto',
    );

    expect($summary['forms'])->toHaveCount(2)
        ->and($summary['forms'][0]['fields'])->toContain('nombre', 'email', 'mensaje')
        ->and($summary['forms'][0]['has_textarea'])->toBeTrue()
        ->and($summary['forms'][0]['action_host'])->toBe('agencia.example')
        ->and($summary['forms'][1]['action_host'])->toBe('other.example')
        ->and(json_encode($summary))->not->toContain('<form');
});

it('fetches html directly with column-fast failure on blocks', function (): void {
    Http::fake([
        'https://agencia.example/*' => Http::response('<html><body><h1>Servicios Laravel</h1><p>Texto.</p></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://bloqueada.example/*' => Http::response('Access Denied', 403),
        'https://desafio.example/*' => Http::response('<html><body>checking your browser before you continue</body></html>', 200, ['Content-Type' => 'text/html']),
    ]);

    $fetcher = new DirectHttpPageFetcher(new OutboundUrlGuard(static fn (string $host): array => ['93.184.216.34']));

    $ok = $fetcher->fetch('https://agencia.example/servicios');

    expect($ok->status)->toBe(FetchStatus::Ok)
        ->and($ok->markdown)->toContain('Servicios Laravel')
        ->and($fetcher->fetch('https://bloqueada.example/x')->status)->toBe(FetchStatus::Blocked)
        ->and($fetcher->fetch('https://desafio.example/x')->status)->toBe(FetchStatus::Blocked)
        ->and($fetcher->fetch('https://www.linkedin.com/company/x')->status)->toBe(FetchStatus::Failed);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'linkedin.com'));
});

it('sends proxy basic with storeInCache false and verifies proxyUsed', function (): void {
    config()->set('services.firecrawl.api_key', 'test-key');
    FirecrawlPageFetcher::enable();

    Http::fake([
        'https://api.firecrawl.dev/v2/scrape' => Http::response([
            'data' => ['markdown' => '# Agencia', 'html' => '<h1>Agencia</h1>'],
            'metadata' => ['proxyUsed' => 'basic'],
        ], 200),
    ]);

    $fetcher = new FirecrawlPageFetcher(
        new OutboundUrlGuard(static fn (string $host): array => ['93.184.216.34']),
        app(CircuitBreakerInterface::class),
    );

    $result = $fetcher->fetch('https://agencia.example/');

    expect($result->status)->toBe(FetchStatus::Ok)
        ->and($result->markdown)->toContain('Agencia');

    Http::assertSent(function (Request $request): bool {
        return $request['proxy'] === 'basic' && $request['storeInCache'] === false;
    });
});

it('discards enhanced-proxy content and disables firecrawl', function (): void {
    config()->set('services.firecrawl.api_key', 'test-key');
    FirecrawlPageFetcher::enable();

    Http::fake([
        'https://api.firecrawl.dev/v2/scrape' => Http::response([
            'data' => ['markdown' => '# Agencia'],
            'metadata' => ['proxyUsed' => 'enhanced'],
        ], 200),
    ]);

    $fetcher = new FirecrawlPageFetcher(
        new OutboundUrlGuard(static fn (string $host): array => ['93.184.216.34']),
        app(CircuitBreakerInterface::class),
    );

    expect($fetcher->fetch('https://agencia.example/')->status)->toBe(FetchStatus::ProxyMismatch)
        ->and(FirecrawlPageFetcher::isDisabled())->toBeTrue()
        ->and($fetcher->fetch('https://agencia.example/otra')->status)->toBe(FetchStatus::Failed);

    FirecrawlPageFetcher::enable();
});

it('walks the ladder without ever escalating a block', function (): void {
    Http::fake([
        'https://agencia.example/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://agencia.example/*' => Http::response('<html><body><h1>Hola</h1></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://bloqueada.example/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://bloqueada.example/*' => Http::response('Forbidden', 403),
        'https://cerrada.example/robots.txt' => Http::response("User-agent: *\nDisallow: /\n", 200),
    ]);

    $company = fetchCompany();

    expect(ladder()->fetch($company->id, 'https://agencia.example/')->succeeded())->toBeTrue()
        ->and(ladder()->fetch($company->id, 'https://bloqueada.example/')->status)->toBe(FetchStatus::Blocked)
        ->and(ladder()->fetch($company->id, 'https://cerrada.example/')->status)->toBe(FetchStatus::SkippedRobots)
        ->and(ladder()->fetch($company->id, 'https://www.linkedin.com/company/x')->status)->toBe(FetchStatus::Failed);

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'firecrawl'));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'linkedin.com/company'));

    expect(ScoutFetchAttemptEloquentModel::query()
        ->where('company_id', $company->id)->count())->toBeGreaterThanOrEqual(3);
});

it('rescues empty spas through firecrawl exactly once', function (): void {
    config()->set('services.firecrawl.api_key', 'test-key');
    FirecrawlPageFetcher::enable();

    Http::fake([
        'https://spa.example/robots.txt' => Http::response("User-agent: *\nDisallow:\n", 200),
        'https://spa.example/*' => Http::response('<html><body><div id="app"></div><script src="/x.js"></script></body></html>', 200, ['Content-Type' => 'text/html']),
        'https://api.firecrawl.dev/v2/scrape' => Http::response([
            'data' => ['markdown' => '# SPA rescatada'],
            'metadata' => ['proxyUsed' => 'basic'],
        ], 200),
    ]);

    $result = ladder()->fetch(fetchCompany()->id, 'https://spa.example/');

    expect($result->succeeded())->toBeTrue()
        ->and($result->markdown)->toContain('rescatada');

    FirecrawlPageFetcher::enable();
});

it('tracks budgets atomically and exposes them over http', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    $ledger = new BudgetLedger;
    $ledger->spend(BudgetCategory::Search, 500_000);

    $shown = $this->actingAs($admin)->getJson('/data/admin/lead-scout/budgets')->assertOk()->json();

    expect($shown['data']['period'])->toBe(BudgetLedger::period());

    $this->actingAs($admin)
        ->putJson('/data/admin/lead-scout/budgets', ['budgets' => [['category' => 'search', 'limit_eur' => -5]]])
        ->assertUnprocessable();

    $this->actingAs($admin)
        ->putJson('/data/admin/lead-scout/budgets', ['budgets' => [['category' => 'search', 'limit_eur' => 12]]])
        ->assertOk();

    expect((new BudgetLedger)->status(BudgetCategory::Search)['limit_micros'])->toBe(12_000_000);
});
