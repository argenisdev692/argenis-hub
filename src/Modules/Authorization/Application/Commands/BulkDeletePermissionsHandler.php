<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Domain\Ports\PermissionRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Soft-deletes a set of permissions by UUID. Authorization
 * (permission:BULK_DELETE_PERMISSIONS) is enforced at the route.
 */
final readonly class BulkDeletePermissionsHandler
{
    public function __construct(private PermissionRepositoryPort $permissions) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn () => $this->permissions->bulkSoftDeleteByUuid($data->uuids));
    }
}
