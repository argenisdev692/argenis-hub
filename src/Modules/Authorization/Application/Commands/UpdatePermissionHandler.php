<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Application\DTOs\PermissionData;
use Modules\Authorization\Domain\Ports\PermissionRepositoryPort;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;

/**
 * Renames a permission. Authorization (permission:UPDATE_PERMISSIONS) is enforced
 * at the route.
 */
final readonly class UpdatePermissionHandler
{
    public function __construct(private PermissionRepositoryPort $permissions) {}

    public function handle(Permission $permission, PermissionData $data): Permission
    {
        return DB::transaction(fn () => $this->permissions->update($permission, ['name' => $data->name |> trim(...)]));
    }
}
