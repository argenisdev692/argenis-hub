<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Authorization\Application\DTOs\RoleFilterData;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Modules\Authorization\Infrastructure\Persistence\Concerns\FlushesPermissionCache;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;
use Shared\Infrastructure\Persistence\Concerns\BulkSoftDeletesByUuid;

/**
 * Eloquent adapter for {@see RoleRepositoryPort}. Bulk soft-delete/restore come
 * from the Shared {@see BulkSoftDeletesByUuid} trait (DRY); each mutating path
 * flushes Spatie's permission cache since soft-delete/restore run as mass updates
 * that bypass model events.
 */
final class EloquentRoleRepository implements RoleRepositoryPort
{
    use BulkSoftDeletesByUuid {
        bulkSoftDeleteByUuid as private bulkSoftDeleteByUuidBase;
        bulkRestoreByUuid as private bulkRestoreByUuidBase;
    }
    use FlushesPermissionCache;

    /**
     * @return class-string<Role>
     */
    protected function model(): string
    {
        return Role::class;
    }

    public function paginate(RoleFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return Role::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->withCount('permissions')
            ->with(['permissions:id,name'])
            ->select(['id', 'uuid', 'name', 'guard_name', 'created_at', 'deleted_at'])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): ?Role
    {
        return Role::withTrashed()
            ->with(['permissions:id,name'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function firstProtectedName(array $uuids, array $protectedNames): ?string
    {
        $name = Role::query()
            ->whereIn('uuid', $uuids)
            ->whereIn('name', $protectedNames)
            ->value('name');

        return $name !== null ? (string) $name : null;
    }

    public function allAssignableNames(): array
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    public function create(array $attributes): Role
    {
        return Role::query()->create($attributes);
    }

    public function update(Role $role, array $attributes): Role
    {
        $role->update($attributes);

        return $role->refresh();
    }

    public function syncPermissions(Role $role, array $permissionNames): Role
    {
        $role->syncPermissions($permissionNames);

        return $role->refresh()->load('permissions:id,name');
    }

    public function softDelete(string $uuid): bool
    {
        $deleted = (bool) Role::query()->where('uuid', $uuid)->delete();
        $this->flushPermissionCache();

        return $deleted;
    }

    public function restore(string $uuid): bool
    {
        $restored = (bool) Role::onlyTrashed()->where('uuid', $uuid)->restore();
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
