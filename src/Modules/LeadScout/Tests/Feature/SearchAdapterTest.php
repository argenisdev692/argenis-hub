<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\LeadScout\Application\Commands\UpdateBudgetsHandler;
use Modules\LeadScout\Application\DTOs\UpdateBudgetsData;
use Modules\LeadScout\Domain\Enums\SearchStatus;
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;
use Modules\LeadScout\Domain\Exceptions\RejectedSearchQueryException;
use Modules\LeadScout\Domain\Ports\SearchPort;
use Modules\LeadScout\Domain\ValueObjects\SearchQuery;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSearchQueryEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    config()->set('services.tavily.api_key', 'test-key');
    config()->set('services.tavily.url', 'https://api.tavily.com/search');
    config()->set('services.tavily.max_results', 5);
});

function tavilyHits(array $urls): array
{
    return ['results' => array_map(
        static fn (string $url): array => ['title' => 'Result', 'url' => $url, 'content' => 'Snippet', 'score' => 0.9],
        $urls,
    )];
}

function searchPort(): SearchPort
{
    return app(SearchPort::class);
}

function discoverQuery(string $text): SearchQuery
{
    return new SearchQuery($text, 'discovery', 'wave1', 'agencia desarrollo Laravel {place}', 'ES', 'advanced', 10);
}

it('caches repeated queries and sends the denylist with country', function (): void {
    Http::fake(['https://api.tavily.com/search' => Http::response(tavilyHits(['https://agencia.example/']), 200)]);

    $first = searchPort()->search(discoverQuery('agencia desarrollo Laravel ES'));

    expect($first)->toHaveCount(1);
    Http::assertSentCount(1);

    $second = searchPort()->search(discoverQuery('agencia desarrollo Laravel ES'));

    expect($second)->toHaveCount(1);
    Http::assertSentCount(1);

    expect(Http::recorded(function (Request $request): bool {
        return str_contains($request->url(), 'api.tavily.com');
    })[0][0]->data())->toMatchArray([
        'country' => 'spain',
        'search_depth' => 'advanced',
        'auto_parameters' => false,
    ]);
    expect(Http::recorded(function (Request $request): bool {
        return str_contains($request->url(), 'api.tavily.com');
    })[0][0]['exclude_domains'])->toContain('linkedin.com');
});

it('rejects professional networks and person-seeking queries before any call', function (): void {
    Http::fake();

    foreach (['CTO agencia site:linkedin.com', 'João Silva, CEO da agência'] as $text) {
        try {
            searchPort()->search(new SearchQuery($text, 'discovery'));
            $this->fail("Expected rejection for: {$text}");
        } catch (RejectedSearchQueryException) {
        }
    }

    Http::assertNothingSent();
});

it('stops with quota exhausted after repeated 432s and spends no budget twice', function (): void {
    Http::fake(['https://api.tavily.com/search' => Http::response('quota', 432)]);

    for ($i = 0; $i < 5; $i++) {
        expect(searchPort()->search(discoverQuery("agencia desarrollo Laravel ES {$i}")))->toBeEmpty();
    }

    expect(searchPort()->search(discoverQuery('agencia desarrollo Laravel ES final')))->toBeEmpty();
    expect(ScoutSearchQueryEloquentModel::query()->where('status', SearchStatus::QuotaExhausted->value)->exists())->toBeTrue();
});

it('refuses paid search when the search budget is exhausted', function (): void {
    Http::fake();

    app(UpdateBudgetsHandler::class)->handle(
        UpdateBudgetsData::from(['budgets' => [['category' => 'search', 'limit_eur' => 0]]]),
    );

    try {
        searchPort()->search(discoverQuery('agencia desarrollo Laravel ES'));
        $this->fail('Expected BudgetExceededException.');
    } catch (BudgetExceededException) {
        expect(ScoutSearchQueryEloquentModel::query()->where('status', SearchStatus::QuotaExhausted->value)->exists())->toBeTrue();
    }

    Http::assertNothingSent();
});
