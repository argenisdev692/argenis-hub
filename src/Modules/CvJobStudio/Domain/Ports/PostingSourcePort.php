<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

/**
 * Discovery source (FR-9, NFR-12). Returns candidates with their provider and
 * query, a first-published date or an explicit unknown (FR-40), and the
 * discovery channel. Swapping a provider touches no domain logic.
 */
interface PostingSourcePort
{
    /**
     * @return array{postings: list<array{url: string, title: string, snippet: string|null, employer: string|null, location: string|null, posted_at: string|null, posted_at_source: string|null, discovery_channel: string}>, query: string, cost_micros: int}
     */
    public function harvest(string $query, int $limit): array;

    public function sourceName(): string;
}
