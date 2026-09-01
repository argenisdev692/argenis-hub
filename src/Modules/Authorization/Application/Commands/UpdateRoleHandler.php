<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Application\DTOs\RoleData;
use Modules\Authorization\Domain\Exceptions\ProtectedRoleException;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Modules\Authorization\Domain\SystemRoles;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

/**
 * Updates a role's name and re-syncs its permissions. A protected system role
 * (see {@see SystemRoles}) may have its permissions adjusted but never be renamed
 * — the invariant that lifts this module above a plain CRUD.
 */
final readonly class UpdateRoleHandler
{
    public function __construct(private RoleRepositoryPort $roles) {}

    public function handle(Role $role, RoleData $data): Role
    {
        $name = $data->name |> trim(...);

        if (SystemRoles::isProtected($role->name) && $name !== $role->name) {
            throw ProtectedRoleException::cannotModify($role->name);
        }

        // Rename + permission re-sync are two writes — one unit-of-work.
        return DB::transaction(function () use ($role, $name, $data): Role {
            $updated = $this->roles->update($role, ['name' => $name]);

            return $this->roles->syncPermissions($updated, $data->permissions);
        });
    }
}
