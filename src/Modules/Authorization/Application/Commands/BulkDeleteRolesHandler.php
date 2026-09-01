<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Domain\Exceptions\ProtectedRoleException;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Modules\Authorization\Domain\SystemRoles;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Soft-deletes a set of roles by UUID. The whole batch is rejected if it contains
 * any protected system role, so a bulk action can never bypass the invariant.
 * Authorization (permission:BULK_DELETE_ROLES) is enforced at the route.
 */
final readonly class BulkDeleteRolesHandler
{
    public function __construct(private RoleRepositoryPort $roles) {}

    public function handle(BulkUuidsData $data): int
    {
        $protected = $this->roles->firstProtectedName($data->uuids, SystemRoles::PROTECTED);

        if ($protected !== null) {
            throw ProtectedRoleException::cannotModify($protected);
        }

        return DB::transaction(fn () => $this->roles->bulkSoftDeleteByUuid($data->uuids));
    }
}
