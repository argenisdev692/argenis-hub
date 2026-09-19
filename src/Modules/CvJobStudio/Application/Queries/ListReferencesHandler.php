<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioReferenceData;
use Modules\CvJobStudio\Domain\Ports\StudioPostingRepositoryPort;

/**
 * Unresolved link_only/resolve_only signals (T-158, FR-53): link, title,
 * snippet — unscored, for the candidate to open in their own browser.
 */
final readonly class ListReferencesHandler
{
    public function __construct(private StudioPostingRepositoryPort $postings) {}

    /** @return LengthAwarePaginator<int, StudioReferenceData> */
    #[\NoDiscard]
    public function handle(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->postings->paginateReferences($userId, $perPage)
            ->through(static fn (object $posting): StudioReferenceData => new StudioReferenceData(
                uuid: $posting->uuid,
                title: $posting->title,
                employerName: $posting->employer_name,
                canonicalUrl: $posting->canonical_url,
                source: $posting->source,
                createdAt: $posting->created_at?->toIso8601String(),
            ));
    }
}
