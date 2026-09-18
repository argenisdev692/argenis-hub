<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData;
use Modules\CvJobStudio\Domain\ValueObjects\GateVerdict;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioPostingEloquentModel;

/**
 * Aggregate persistence for postings (+ texts, requirements, gate results).
 * Every query is owner-scoped: holding a permission never implies access to
 * another candidate's postings (OWASP §11, BOLA).
 */
interface StudioPostingRepositoryPort
{
    public function paginate(StudioPostingFilterData $filters, int $perPage, int $userId): LengthAwarePaginator;

    public function findByUuidForUser(string $uuid, int $userId): ?StudioPostingEloquentModel;

    public function create(array $attributes): StudioPostingEloquentModel;

    public function update(StudioPostingEloquentModel $posting, array $attributes): StudioPostingEloquentModel;

    public function softDelete(string $uuid, int $userId): bool;

    public function restore(string $uuid, int $userId): bool;

    public function bulkSoftDeleteForUser(array $uuids, int $userId): int;

    public function bulkRestoreForUser(array $uuids, int $userId): int;

    /**
     * Canonicalise → dedupe on `url_hash` → store text + requirements + gate
     * verdicts, atomically (FR-10, FR-12). A re-ingest refreshes the sighting
     * timestamp instead of duplicating the row (FR-41).
     *
     * @param  array{user_id: int, profile_id: int, canonical_url: string, url_hash: string, source: string|null, employer_name: string|null, title: string, location_text: string|null, remote_scope: string, status: string, discovery_channel: string|null}  $posting
     * @param  list<array{canonical_name: string, tag: string, nature: string}>  $requirements
     * @param  list<GateVerdict>  $verdicts
     */
    public function ingest(array $posting, ?string $text, array $requirements, array $verdicts): StudioPostingEloquentModel;

    public function scoringContext(string $uuid, int $userId): ?StudioPostingEloquentModel;

    /** @return list<array{from: string, to: string, kind: string}> */
    public function confirmedRelations(int $userId): array;

    public function setStatus(string $uuid, int $userId, string $status): bool;

    public function paginateReferences(int $userId, int $perPage): LengthAwarePaginator;

    public function deduplicate(int $userId): int;
}
