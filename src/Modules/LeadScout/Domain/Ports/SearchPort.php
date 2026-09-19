<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\ValueObjects\SearchQuery;
use Modules\LeadScout\Domain\ValueObjects\SearchResult;

/**
 * Paid web search behind cache, guard, budget and breaker (spec US-7).
 * No fallback provider: if search is down, discovery stops with a reason
 * while offers and import continue (plan §9).
 */
interface SearchPort
{
    /**
     * @return list<SearchResult>
     */
    public function search(SearchQuery $query): array;

    /**
     * Credits the companies a query surfaced, for the per-query
     * effectiveness metric (spec US-7, T066).
     */
    public function recordNewCompanies(SearchQuery $query, int $count): void;
}
