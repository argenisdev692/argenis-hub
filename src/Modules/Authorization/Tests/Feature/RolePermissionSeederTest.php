<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

it('restores a soft-deleted permission and re-grants it to SUPER_ADMIN', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $permission = Permission::query()
        ->where('name', 'VIEW_ANY_RESUME_STUDIOS')
        ->where('guard_name', 'web')
        ->firstOrFail();

    $permission->delete();

    $this->assertSoftDeleted('permissions', ['id' => $permission->id]);

    $this->seed(RolePermissionSeeder::class);

    $this->assertDatabaseHas('permissions', [
        'id' => $permission->id,
        'name' => 'VIEW_ANY_RESUME_STUDIOS',
        'deleted_at' => null,
    ]);

    $superAdminRole = Role::query()->where('name', 'SUPER_ADMIN')->firstOrFail();
    expect($superAdminRole->hasPermissionTo('VIEW_ANY_RESUME_STUDIOS'))->toBeTrue();

    // Asserted through the permission gate, not an HTTP route: the seeder
    // provisions permissions for modules that may not ship a route yet.
    $user = User::factory()->create();
    $user->assignRole('SUPER_ADMIN');

    expect($user->can('VIEW_ANY_RESUME_STUDIOS'))->toBeTrue();
});

it('restores a soft-deleted SUPER_ADMIN role instead of duplicating it', function (): void {
    $this->seed(RolePermissionSeeder::class);

    $role = Role::query()->where('name', 'SUPER_ADMIN')->firstOrFail();
    $originalId = $role->id;
    $role->delete();

    $this->seed(RolePermissionSeeder::class);

    $this->assertDatabaseHas('roles', [
        'id' => $originalId,
        'name' => 'SUPER_ADMIN',
        'deleted_at' => null,
    ]);

    expect(Role::withTrashed()->where('name', 'SUPER_ADMIN')->count())->toBe(1);

    $user = User::factory()->create();
    $user->assignRole('SUPER_ADMIN');

    expect($user->can('VIEW_ANY_RESUME_STUDIOS'))->toBeTrue();
});
