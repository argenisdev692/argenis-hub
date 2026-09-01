<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Authorization\Application\DTOs\PermissionFilterData;
use Modules\Authorization\Domain\Ports\PermissionRepositoryPort;
use Modules\Authorization\Infrastructure\Persistence\Concerns\FlushesPermissionCache;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;
use Shared\Infrastructure\Persistence\Concerns\BulkSoftDeletesByUuid;

/**
 * Eloquent adapter for {@see PermissionRepositoryPort}. See
 * {@see EloquentRoleRepository} for the cache-flush rationale.
 */
final class EloquentPermissionRepository implements PermissionRepositoryPort
{
    use BulkSoftDeletesByUuid {
        bulkSoftDeleteByUuid as private bulkSoftDeleteByUuidBase;
        bulkRestoreByUuid as private bulkRestoreByUuidBase;
    }
    use FlushesPermissionCache;

    /**
     * @return class-string<Permission>
     */
    protected function model(): string
    {
        return Permission::class;
    }

    public function paginate(PermissionFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return Permission::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->withCount('roles')
            ->with('roles:id,name')
            ->select(['id', 'uuid', 'name', 'guard_name', 'created_at', 'deleted_at'])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): ?Permission
    {
        return Permission::withTrashed()
            ->where('uuid', $uuid)
            ->first();
    }

    /**
     * @return array<int, string>
     */
    public function allActiveNames(): array
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function create(array $attributes): Permission
    {
        return Permission::query()->create($attributes);
    }

    public function update(Permission $permission, array $attributes): Permission
    {
        $permission->update($attributes);

        return $permission->refresh();
    }

    public function softDelete(string $uuid): bool
    {
        $deleted = (bool) Permission::query()->where('uuid', $uuid)->delete();
        $this->flushPermissionCache();

        return $deleted;
    }

    public function restore(string $uuid): bool
    {
        $restored = (bool) Permission::onlyTrashed()->where('uuid', $uuid)->restore();
        $this->flushPermissionCache();

        return $restored;
    }

    public function bulkSoftDeleteByUuid(array $uuids): int
    {
        $count = $this->bulkSoftDeleteByUuidBase($uuids);
        $this->flushPermissionCache();

        return $count;
    }

    public function bulkRestoreByUuid(array $uuids): int
    {
        $count = $this->bulkRestoreByUuidBase($uuids);
        $this->flushPermissionCache();

        return $count;
    }
}
