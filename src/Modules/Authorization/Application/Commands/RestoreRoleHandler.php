<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;

/**
 * Restores a suspended role by UUID. Authorization (permission:RESTORE_ROLES) is
 * enforced at the route.
 */
final readonly class RestoreRoleHandler
{
    public function __construct(private RoleRepositoryPort $roles) {}

    public function handle(string $uuid): bool
    {
        return DB::transaction(fn () => $this->roles->restore($uuid));
    }
}
