<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Domain\Ports\PermissionRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Restores a set of suspended permissions by UUID. Authorization
 * (permission:BULK_RESTORE_PERMISSIONS) is enforced at the route.
 */
final readonly class BulkRestorePermissionsHandler
{
    public function __construct(private PermissionRepositoryPort $permissions) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn () => $this->permissions->bulkRestoreByUuid($data->uuids));
    }
}
