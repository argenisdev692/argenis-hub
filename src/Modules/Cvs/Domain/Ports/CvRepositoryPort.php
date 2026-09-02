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
 */
interface CvRepositoryPort
{
    public function paginate(CvFilterData $filters, int $perPage, int $userId): LengthAwarePaginator;

    public function findByUuidForUser(string $uuid, int $userId): ?CvEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CvEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CvEloquentModel $cv, array $attributes): CvEloquentModel;

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

    /**
     * Clears is_primary for every CV owned by $userId except an optional UUID.
     */
    public function clearPrimaryForUser(int $userId, ?string $exceptUuid = null): void;
}
