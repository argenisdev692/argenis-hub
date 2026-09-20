<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use DateTimeImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioPostingData;
use Modules\CvJobStudio\Application\DTOs\StudioPostingDetailData;
use Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData;
use Modules\CvJobStudio\Application\DTOs\StudioReferenceData;
use Modules\CvJobStudio\Domain\Enums\RequirementNature;
use Modules\CvJobStudio\Domain\Enums\RequirementTag;
use Modules\CvJobStudio\Domain\ValueObjects\GateVerdict;
use Modules\CvJobStudio\Domain\ValueObjects\PostingScoringContext;

/**
 * Aggregate persistence for postings (+ texts, requirements, gate results).
 * Every query is owner-scoped: holding a permission never implies access to
 * another candidate's postings (OWASP §11, BOLA).
 */
interface StudioPostingRepositoryPort
{
    /** @return LengthAwarePaginator<int, StudioPostingData> */
    public function paginate(StudioPostingFilterData $filters, int $perPage, int $userId): LengthAwarePaginator;

    /** Soft-deleted postings included — the detail page shows suspended rows too. */
    public function detailForUser(string $uuid, int $userId): ?StudioPostingDetailData;

    public function idForUser(string $uuid, int $userId): ?int;

    public function softDelete(string $uuid, int $userId): bool;

    public function restore(string $uuid, int $userId): bool;

    /** @param  list<string>  $uuids */
    public function bulkSoftDeleteForUser(array $uuids, int $userId): int;

    /** @param  list<string>  $uuids */
    public function bulkRestoreForUser(array $uuids, int $userId): int;

    /**
     * Canonicalise → dedupe on `url_hash` → store text + requirements + gate
     * verdicts, atomically (FR-10, FR-12). A re-ingest refreshes the sighting
     * timestamp instead of duplicating the row (FR-41).
     *
     * @param  array{user_id: int, profile_id: int, canonical_url: string, url_hash: string, source: string|null, employer_name: string|null, title: string, location_text: string|null, remote_scope: string, status: string, discovery_channel: string|null}  $posting
     * @param  list<array{canonical_name: string, tag: string, nature: string}>  $requirements
     * @param  list<GateVerdict>  $verdicts
     * @return int the posting id
     */
    public function ingest(array $posting, ?string $text, array $requirements, array $verdicts): int;

    public function scoringContext(string $uuid, int $userId): ?PostingScoringContext;

    /** @return list<array{from: string, to: string, kind: string}> */
    public function confirmedRelations(int $userId): array;

    public function setStatus(string $uuid, int $userId, string $status): bool;

    /** @return LengthAwarePaginator<int, StudioReferenceData> */
    public function paginateReferences(int $userId, int $perPage): LengthAwarePaginator;

    public function deduplicate(int $userId): int;

    public function canonicalUrlForUser(string $uuid, int $userId): ?string;

    public function recordApplyDestination(string $uuid, int $userId, string $destination, ?string $atsKind): void;

    /** Restores the original source as preferred (T-119). */
    public function unlinkResolution(string $uuid, int $userId): bool;

    /** Marks postings not seen since `$cutoff` as expired (FR-42); returns how many. */
    public function expireUnseenSince(int $userId, DateTimeImmutable $cutoff): int;

    /** Stores candidate-pasted JD text on a `reference` posting and moves it to `new` (FR-53). */
    public function attachPastedText(string $uuid, int $userId, string $text): bool;

    /**
     * Requirement texts used as RAG targets; null when the posting is not the owner's.
     *
     * @return list<string>|null
     */
    public function requirementTargets(string $uuid, int $userId): ?array;

    /** @return list<array{id: int, canonical_name: string, raw_text: string}> */
    public function requirementsForText(int $postingId, int $userId, string $textHash): array;

    /**
     * @param  list<array{canonical_name: string, raw_text: string, tag: RequirementTag, nature: RequirementNature}>  $requirements
     * @param  array{provider: string, model: string, prompt_version: string}  $provenance
     * @return list<array{id: int, canonical_name: string, raw_text: string}>
     */
    public function storeRequirements(int $postingId, int $userId, string $textHash, array $requirements, array $provenance): array;

    public function dismissRequirement(string $uuid, int $userId): bool;
}
