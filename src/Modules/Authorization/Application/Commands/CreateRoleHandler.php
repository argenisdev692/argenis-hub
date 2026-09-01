<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Application\DTOs\RoleData;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

/**
 * Creates a role on the `web` guard and syncs its permission grants in one unit.
 * Authorization (permission:CREATE_ROLES) is enforced at the route — never here.
 */
final readonly class CreateRoleHandler
{
    public function __construct(private RoleRepositoryPort $roles) {}

    #[\NoDiscard]
    public function handle(RoleData $data): Role
    {
        $name = $data->name |> trim(...);

        // Create + permission sync are two writes — one unit-of-work so a failed
        // sync never leaves a role with no grants.
        return DB::transaction(function () use ($name, $data): Role {
            $role = $this->roles->create(['name' => $name, 'guard_name' => 'web']);

            return $this->roles->syncPermissions($role, $data->permissions);
        });
    }
}
