<?php

declare(strict_types=1);

namespace Modules\Cvs\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

/**
 * Persistence contract for the CV aggregate.
 *
 * Every read and write is scoped by `$userId`: a CV is personal data, so
 * ownership — not merely holding `VIEW_CVS` — is what grants access (OWASP §11,
 * BOLA). The owner is a required parameter rather than an ambient `auth()` call
 * so the check cannot be forgotten at a call site.
 *
 * Transactions live behind this port, not in the handlers: the Application
 * layer may not reach for the `DB` facade (BACKEND-PHP §5).
 */
interface CvRepositoryPort
{
    public function paginate(CvFilterData $filters, int $perPage, int $userId): LengthAwarePaginator;

    public function findByUuidForUser(string $uuid, int $userId): ?CvEloquentModel;

    /**
     * Owner-scoped lookup by internal id — for cross-module reads that only
     * hold the legacy `cv_id` reference (e.g. Studio version lineage).
     */
    public function findByIdForUser(int $id, int $userId): ?CvEloquentModel;

    /**
     * The owner's current primary CV, if any — used to inherit attributes
     * (niche) when the originating CV is gone.
     */
    public function findPrimaryForUser(int $userId): ?CvEloquentModel;

    /**
     * Persists a CV. When `is_primary` is true, the owner's other CVs are
     * demoted in the same transaction so a user never holds two primaries.
     *
     * @param  array<string, mixed>  $attributes  must include `user_id`
     */
    public function create(array $attributes): CvEloquentModel;

    /**
     * Same single-primary guarantee as {@see self::create()}.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(CvEloquentModel $cv, array $attributes): CvEloquentModel;

    /**
     * Soft deletes through the model so the activity log records it — a
     * query-builder `delete()` dispatches no model events.
     */
    public function softDelete(string $uuid, int $userId): bool;

    public function restore(string $uuid, int $userId): bool;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkSoftDeleteForUser(array $uuids, int $userId): int;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkRestoreForUser(array $uuids, int $userId): int;
}
