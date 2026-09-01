<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Queries;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

final readonly class GetRoleHandler
{
    public function __construct(private RoleRepositoryPort $roles) {}

    public function handle(string $uuid): Role
    {
        return $this->roles->findByUuid($uuid)
            ?? throw (new ModelNotFoundException)->setModel(Role::class, [$uuid]);
    }
}
