<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Domain\Ports\PermissionRepositoryPort;

/**
 * Soft-deletes ("suspends") a permission by UUID — it is immediately dropped from
 * Spatie's cached permission set, so any role checking it loses access until
 * restored. Authorization (permission:DELETE_PERMISSIONS) is enforced at the route.
 */
final readonly class DeletePermissionHandler
{
    public function __construct(private PermissionRepositoryPort $permissions) {}

    public function handle(string $uuid): bool
    {
        return DB::transaction(fn () => $this->permissions->softDelete($uuid));
    }
}
