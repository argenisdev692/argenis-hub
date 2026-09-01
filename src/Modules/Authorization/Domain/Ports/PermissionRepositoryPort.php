<?php

declare(strict_types=1);

namespace Modules\Authorization\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Authorization\Application\DTOs\PermissionFilterData;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;

interface PermissionRepositoryPort
{
    /**
     * @return LengthAwarePaginator<int, Permission>
     */
    public function paginate(PermissionFilterData $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Permission;

    /**
     * Every active (non-suspended) permission name, ordered — the catalogue the
     * role create/edit form syncs against.
     *
     * @return array<int, string>
     */
    public function allActiveNames(): array;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Permission;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Permission $permission, array $attributes): Permission;

    public function softDelete(string $uuid): bool;

    public function restore(string $uuid): bool;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkSoftDeleteByUuid(array $uuids): int;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkRestoreByUuid(array $uuids): int;
}
