<?php

declare(strict_types=1);

use Modules\Authorization\Application\Commands\BulkDeleteRolesHandler;
use Modules\Authorization\Domain\Exceptions\ProtectedRoleException;
use Modules\Authorization\Domain\Ports\RoleRepositoryPort;
use Modules\Authorization\Domain\SystemRoles;
use Shared\Application\DTOs\BulkUuidsData;

it('soft-deletes the batch when it holds no protected role', function (): void {
    $uuids = ['11111111-1111-1111-1111-111111111111', '22222222-2222-2222-2222-222222222222'];

    $repository = Mockery::mock(RoleRepositoryPort::class);
    $repository->shouldReceive('firstProtectedName')
        ->once()
        ->with($uuids, SystemRoles::PROTECTED)
        ->andReturn(null);
    $repository->shouldReceive('bulkSoftDeleteByUuid')
        ->once()
        ->with($uuids)
        ->andReturn(2);

    expect((new BulkDeleteRolesHandler($repository))->handle(new BulkUuidsData(uuids: $uuids)))->toBe(2);
});

it('rejects the whole batch when it holds a protected role', function (): void {
    $uuids = ['11111111-1111-1111-1111-111111111111', '33333333-3333-3333-3333-333333333333'];

    $repository = Mockery::mock(RoleRepositoryPort::class);
    $repository->shouldReceive('firstProtectedName')
        ->once()
        ->with($uuids, SystemRoles::PROTECTED)
        ->andReturn('ADMIN');
    // The invariant must short-circuit before any mutation runs.
    $repository->shouldNotReceive('bulkSoftDeleteByUuid');

    (new BulkDeleteRolesHandler($repository))->handle(new BulkUuidsData(uuids: $uuids));
})->throws(ProtectedRoleException::class);
