<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Authorization\Domain\Ports\PermissionRepositoryPort;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;

final readonly class GetPermissionHandler
{
    public function __construct(private PermissionRepositoryPort $permissions) {}

    public function handle(string $uuid): Permission
    {
        return $this->permissions->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(Permission::class, [$uuid]);
    }
}
