<?php

declare(strict_types=1);

use Modules\Authorization\Application\Commands\CreateRoleHandler;
use Modules\Authorization\Application\DTOs\RoleData;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

it('trims the name, creates the role, then syncs its permissions', function (): void {
    $role = Role::factory()->make(['name' => 'EDITOR', 'guard_name' => 'web']);

    $repository = Mockery::mock(RoleRepositoryPort::class);
    $repository->shouldReceive('create')
        ->once()
        ->with(['name' => 'EDITOR', 'guard_name' => 'web'])
        ->andReturn($role);
    $repository->shouldReceive('syncPermissions')
        ->once()
        ->with($role, ['VIEW_ROLES', 'CREATE_ROLES'])
        ->andReturn($role);

    $result = (new CreateRoleHandler($repository))
        ->handle(new RoleData(name: '  EDITOR  ', permissions: ['VIEW_ROLES', 'CREATE_ROLES']));

    expect($result)->toBe($role);
});
