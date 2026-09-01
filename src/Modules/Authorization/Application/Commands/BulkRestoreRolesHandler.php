<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Restores a set of suspended roles by UUID. Authorization
 * (permission:BULK_RESTORE_ROLES) is enforced at the route.
 */
final readonly class BulkRestoreRolesHandler
{
    public function __construct(private RoleRepositoryPort $roles) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn () => $this->roles->bulkRestoreByUuid($data->uuids));
    }
}
