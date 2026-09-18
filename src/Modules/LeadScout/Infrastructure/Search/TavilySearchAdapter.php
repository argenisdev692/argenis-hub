<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Search;

use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Enums\SearchStatus;
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;
use Modules\LeadScout\Domain\Exceptions\RejectedSearchQueryException;
use Modules\LeadScout\Domain\Ports\SearchPort;
use Modules\LeadScout\Domain\ValueObjects\SearchQuery;
use Modules\LeadScout\Domain\ValueObjects\SearchResult;
use Modules\LeadScout\Infrastructure\Budgets\BudgetLedger;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSearchQueryEloquentModel;
use Shared\Infrastructure\Research\TavilyClientInterface;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerState;

/**
 * Paid search behind guard → cache → budget → breaker (spec US-7, T042).
 * Discovery uses `advanced` (relevance), company resolution `basic`.
 * `exclude_domains` carries the denylist to the provider (≤ 150); the
 * local filter stays as second barrier. `country` follows the wave;
 * `auto_parameters` stays off so depth (and cost) never escalates silently.
 * No fallback provider: without search, discovery stops with a reason.
 */
final readonly class TavilySearchAdapter implements SearchPort
{
    /**
     * @var list<string>
     */
    private const array QUERY_DENY = [
        'linkedin', 'site:linkedin.com', 'xing', 'apollo', 'zoominfo',
        'rocketreach', 'lusha', 'kaspr', 'hunter',
    ];

    /**
     * @var list<string>
     */
    private const array PERSON_SEEKING = [
        'ceo', 'cto', 'founder', 'fundador', 'cofundador', 'co-founder',
        'socio-gerente', 'sócio-gerente', 'director', 'diretor', 'head of',
        'quién es', 'quien es', 'who is', 'email', 'teléfono', 'telefone', 'phone',
    ];

    public function __construct(
        private TavilyClientInterface $tavily,
        private CircuitBreakerInterface $breaker,
        private BudgetLedger $budgets,
    ) {}

    /**
     * @throws RejectedSearchQueryException
     */
    public function search(SearchQuery $query): array
    {
        self::assertAllowed($query->text);

        $hash = hash('sha256', mb_strtolower(trim($query->text)).'|'.$query->depth.'|'.($query->country ?? ''));
        $freshBefore = now()->subDays((int) config('lead-scout.discovery.search_cache_days', 30));

        $cached = ScoutSearchQueryEloquentModel::query()
            ->where('query_hash', $hash)
            ->where('created_at', '>=', $freshBefore)
            ->orderByDesc('id')
            ->first();

        if ($cached !== null && is_array($cached->results)) {
            return array_map(self::toResult(...), $cached->results);
        }

        $cost = $this->costMicros($query->depth);

        try {
            $this->budgets->ensure(BudgetCategory::Search, $cost);
        } catch (BudgetExceededException $e) {
            $this->store($query, $hash, [], SearchStatus::QuotaExhausted, 0);

            throw $e;
        }

        $raw = $this->tavily->search(
            [$query->text],
            null,
            $query->depth,
            (array) config('lead-scout.result_domain_denylist', []),
            $query->country,
        );

        $results = [];

        foreach (array_slice($raw, 0, $query->maxResults) as $row) {
            if (trim((string) ($row['url'] ?? '')) === '') {
                continue;
            }

            $results[] = new SearchResult(
                title: mb_substr((string) ($row['title'] ?? ''), 0, 255),
                url: (string) $row['url'],
                snippet: mb_substr((string) ($row['content'] ?? ''), 0, 2000),
                score: (float) ($row['score'] ?? 0),
            );
        }

        // The shared client degrades to [] on failure; an open breaker right
        // after an empty answer is the only quota signal visible here.
        $status = $results !== [] || $this->breaker->state('tavily') !== CircuitBreakerState::Open
            ? SearchStatus::Ok
            : SearchStatus::QuotaExhausted;

        $this->store($query, $hash, $results, $status, $cost);
        $this->budgets->spend(BudgetCategory::Search, $cost);

        return $results;
    }

    /**
     * @throws RejectedSearchQueryException
     */
    public static function assertAllowed(string $query): void
    {
        $lower = mb_strtolower($query);

        foreach ([...self::QUERY_DENY, ...(array) config('lead-scout.query_denylist', [])] as $denied) {
            $denied = mb_strtolower(trim((string) $denied));

            if ($denied !== '' && str_contains($lower, $denied)) {
                throw new RejectedSearchQueryException("Query targets a forbidden source: {$denied}.");
            }
        }

        foreach (self::PERSON_SEEKING as $token) {
            if (str_contains($lower, $token)) {
                throw new RejectedSearchQueryException("Query looks person-seeking ({$token}): templates and company names only.");
            }
        }
    }

    /**
     * @param  array{title: string, url: string, content: string, score: float}  $row
     */
    private static function toResult(array $row): SearchResult
    {
        return new SearchResult(
            title: (string) ($row['title'] ?? ''),
            url: (string) $row['url'],
            snippet: (string) ($row['content'] ?? ''),
            score: (float) ($row['score'] ?? 0),
        );
    }

    /**
     * @param  list<SearchResult>  $results
     */
    private function store(SearchQuery $query, string $hash, array $results, SearchStatus $status, int $cost): void
    {
        ScoutSearchQueryEloquentModel::query()->create([
            'query_hash' => $hash,
            'query_text' => mb_substr($query->text, 0, 512),
            'search_depth' => $query->depth,
            'purpose' => $query->purpose,
            'discovery_wave' => $query->wave,
            'family' => $query->family,
            'country' => $query->country,
            'results' => array_map(static fn (SearchResult $r): array => [
                'title' => $r->title, 'url' => $r->url, 'content' => $r->snippet, 'score' => $r->score,
            ], $results),
            'status' => $status->value,
            'cost_micros' => $cost,
        ]);
    }

    private function costMicros(string $depth): int
    {
        $key = $depth === 'basic' ? 'tavily_basic_eur' : 'tavily_advanced_eur';

        return (int) ((float) config("lead-scout.costs.{$key}", 0.01) * 1_000_000);
    }
}
