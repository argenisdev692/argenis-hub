<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

/**
 * Every query starts from `ownedBy($userId)` — see {@see CvRepositoryPort} for
 * why ownership, not permission alone, gates access to a CV.
 */
final readonly class EloquentCvRepository implements CvRepositoryPort
{
    /**
     * Columns the list surface needs. `raw_text` and `file_path` are excluded
     * deliberately: `raw_text` is a longText PII column that would dominate the
     * payload, and neither is ever serialized (OWASP §12).
     *
     * @var list<string>
     */
    private const array LIST_COLUMNS = [
        'id',
        'uuid',
        'user_id',
        'title',
        'niche',
        'is_primary',
        'file_type',
        'original_filename',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function paginate(CvFilterData $filters, int $perPage, int $userId): LengthAwarePaginator
    {
        return CvEloquentModel::query()
            ->ownedBy($userId)
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->with('user:id,first_name,last_name')
            ->select(self::LIST_COLUMNS)
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuidForUser(string $uuid, int $userId): ?CvEloquentModel
    {
        return CvEloquentModel::withTrashed()
            ->ownedBy($userId)
            ->with('user:id,first_name,last_name')
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $attributes): CvEloquentModel
    {
        return CvEloquentModel::query()->create($attributes);
    }

    public function update(CvEloquentModel $cv, array $attributes): CvEloquentModel
    {
        $cv->update($attributes);

        return $cv->refresh();
    }

    public function softDelete(string $uuid, int $userId): bool
    {
        return (bool) CvEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->delete();
    }

    public function restore(string $uuid, int $userId): bool
    {
        return (bool) CvEloquentModel::onlyTrashed()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->restore();
    }

    public function bulkSoftDeleteForUser(array $uuids, int $userId): int
    {
        if ($uuids === []) {
            return 0;
        }

        return CvEloquentModel::query()
            ->ownedBy($userId)
            ->whereIn('uuid', $uuids)
            ->delete();
    }

    public function bulkRestoreForUser(array $uuids, int $userId): int
    {
        if ($uuids === []) {
            return 0;
        }

        return CvEloquentModel::onlyTrashed()
            ->ownedBy($userId)
            ->whereIn('uuid', $uuids)
            ->restore();
    }

    public function clearPrimaryForUser(int $userId, ?string $exceptUuid = null): void
    {
        CvEloquentModel::query()
            ->ownedBy($userId)
            ->where('is_primary', true)
            ->when($exceptUuid !== null, fn ($q) => $q->where('uuid', '!=', $exceptUuid))
            ->update(['is_primary' => false]);
    }
}
