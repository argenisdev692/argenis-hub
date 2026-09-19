<?php

declare(strict_types=1);

namespace Modules\Cvs\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Cvs\Application\DTOs\CvFilterData;
use Modules\Cvs\Domain\Ports\CvRepositoryPort;
use Modules\Cvs\Infrastructure\Persistence\Eloquent\Models\CvEloquentModel;

/**
 * Every query starts from `ownedBy($userId)` — see {@see CvRepositoryPort} for
 * why ownership, not permission alone, gates access to a CV.
 *
 * Deletes and restores go through the model instance, never a mass query:
 * Laravel does not dispatch model events for mass soft deletes / restores, so a
 * query-builder `delete()` would silently skip the `LogsActivity` audit trail.
 */
final readonly class EloquentCvRepository implements CvRepositoryPort
{
    /**
     * Columns the list surface needs. `raw_text` and `file_path` are excluded
     * deliberately: `raw_text` is a longText PII column that would dominate the
     * payload, and neither is ever serialized (OWASP §12). It also covers every
     * `logOnly` attribute, so the lifecycle log can read them under strict mode.
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
        'source',
        'language',
        'parent_cv_uuid',
        'studio_version_uuid',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function paginate(CvFilterData $filters, int $perPage, int $userId): LengthAwarePaginator
    {
        return CvEloquentModel::query()
            ->ownedBy($userId)
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

    public function findByIdForUser(int $id, int $userId): ?CvEloquentModel
    {
        return CvEloquentModel::query()
            ->ownedBy($userId)
            ->where('id', $id)
            ->first(['id', 'uuid', 'niche']);
    }

    public function findPrimaryForUser(int $userId): ?CvEloquentModel
    {
        return CvEloquentModel::query()
            ->ownedBy($userId)
            ->where('is_primary', true)
            ->orderByDesc('created_at')
            ->first(['id', 'uuid', 'niche']);
    }

    public function create(array $attributes): CvEloquentModel
    {
        return DB::transaction(function () use ($attributes): CvEloquentModel {
            if (($attributes['is_primary'] ?? false) === true) {
                $this->demoteOtherPrimaries((int) $attributes['user_id']);
            }

            return CvEloquentModel::query()->create($attributes);
        });
    }

    public function update(CvEloquentModel $cv, array $attributes): CvEloquentModel
    {
        return DB::transaction(function () use ($cv, $attributes): CvEloquentModel {
            if (($attributes['is_primary'] ?? false) === true) {
                $this->demoteOtherPrimaries($cv->user_id, $cv->uuid);
            }

            $cv->update($attributes);

            return $cv->refresh();
        });
    }

    public function softDelete(string $uuid, int $userId): bool
    {
        return DB::transaction(static fn (): bool => (bool) CvEloquentModel::query()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first(self::LIST_COLUMNS)
            ?->delete());
    }

    public function restore(string $uuid, int $userId): bool
    {
        return DB::transaction(static fn (): bool => (bool) CvEloquentModel::onlyTrashed()
            ->ownedBy($userId)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->first(self::LIST_COLUMNS)
            ?->restore());
    }

    public function bulkSoftDeleteForUser(array $uuids, int $userId): int
    {
        if ($uuids === []) {
            return 0;
        }

        return DB::transaction(static fn (): int => CvEloquentModel::query()
            ->ownedBy($userId)
            ->whereIn('uuid', $uuids)
            ->lockForUpdate()
            ->get(self::LIST_COLUMNS)
            ->filter(static fn (CvEloquentModel $cv): bool => (bool) $cv->delete())
            ->count());
    }

    public function bulkRestoreForUser(array $uuids, int $userId): int
    {
        if ($uuids === []) {
            return 0;
        }

        return DB::transaction(static fn (): int => CvEloquentModel::onlyTrashed()
            ->ownedBy($userId)
            ->whereIn('uuid', $uuids)
            ->lockForUpdate()
            ->get(self::LIST_COLUMNS)
            ->filter(static fn (CvEloquentModel $cv): bool => $cv->restore())
            ->count());
    }

    /**
     * Clears `is_primary` on the owner's CVs, optionally sparing one UUID. A
     * mass update is fine here: flipping the flag off is a side effect of the
     * audited write that sets the new primary.
     */
    private function demoteOtherPrimaries(int $userId, ?string $exceptUuid = null): void
    {
        CvEloquentModel::query()
            ->ownedBy($userId)
            ->where('is_primary', true)
            ->when($exceptUuid !== null, fn ($q) => $q->where('uuid', '!=', $exceptUuid))
            ->update(['is_primary' => false]);
    }
}
