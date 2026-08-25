<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Research;

/**
 * Real-time web research contract, used to ground AI content generation in
 * current trends/data instead of stale model knowledge (see Post module
 * PostContentGeneratorPort). Cross-cutting — lives in Shared so a future module
 * can reuse it without duplicating the HTTP client.
 */
interface TavilyClientInterface
{
    /**
     * Run up to 4 queries and return their pooled, flattened results. Degrades
     * to an empty array (never throws) so a research outage never blocks
     * generation — callers simply fall back to the model's own knowledge.
     *
     * @param  list<string>  $queries
     * @return list<array{title: string, url: string, content: string, score: float}>
     */
    public function search(array $queries): array;
}
