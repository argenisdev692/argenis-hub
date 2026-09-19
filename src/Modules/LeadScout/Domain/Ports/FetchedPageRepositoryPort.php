<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use DateTimeImmutable;
use Modules\LeadScout\Domain\Entities\FetchedPage;
use Modules\LeadScout\Domain\Enums\PageType;

interface FetchedPageRepositoryPort
{
    /**
     * Stores (or refreshes) a page. The raw HTML is only used to derive the
     * forms summary and is never kept.
     */
    public function store(
        int $companyId,
        string $url,
        ?PageType $type,
        ?string $markdown,
        ?string $html,
        DateTimeImmutable $fetchedAt,
    ): void;

    /**
     * Pages that still hold markdown, oldest first.
     *
     * @return list<FetchedPage>
     */
    public function withContent(int $companyId): array;

    /**
     * @return list<string>
     */
    public function urlsFor(int $companyId): array;

    /** Typed pages with markdown — the evidence count for enrichment. */
    public function countEvidencePages(int $companyId): int;

    /**
     * Drops the stored markdown of pages fetched before `$cutoff` (spec FR-27).
     *
     * @return int pages pruned
     */
    public function pruneContentFetchedBefore(DateTimeImmutable $cutoff, DateTimeImmutable $prunedAt): int;
}
