<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Modules\Authorization\Domain\Exceptions\ProtectedRoleException;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Modules\Authorization\Domain\SystemRoles;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

/**
 * Soft-deletes ("suspends") a single role by UUID. Protected system roles cannot
 * be deleted. Authorization (permission:DELETE_ROLES) is enforced at the route.
 */
final readonly class DeleteRoleHandler
{
    public function __construct(private RoleRepositoryPort $roles) {}

    public function handle(string $uuid): bool
    {
        $role = $this->roles->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(Role::class, [$uuid]);

        if (SystemRoles::isProtected($role->name)) {
            throw ProtectedRoleException::cannotModify($role->name);
        }

        return DB::transaction(fn () => $this->roles->softDelete($uuid));
    }
}
