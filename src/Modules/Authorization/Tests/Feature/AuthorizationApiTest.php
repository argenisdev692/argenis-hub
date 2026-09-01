<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function apiActor(array $permissions = []): User
{
    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    Sanctum::actingAs($user);

    return $user;
}

it('rejects an unauthenticated call to the role API', function (): void {
    $this->getJson('/api/roles')->assertUnauthorized();
    $this->getJson('/api/permissions')->assertUnauthorized();
});

it('lists roles for a token holding VIEW_ANY_ROLES', function (): void {
    apiActor(['VIEW_ANY_ROLES']);
    Role::factory()->create(['name' => 'API_ROLE']);

    $this->getJson('/api/roles?search=API_ROLE')
        ->assertOk()
        ->assertJsonFragment(['name' => 'API_ROLE']);
});

it('forbids the role API to a token without VIEW_ANY_ROLES', function (): void {
    apiActor(['VIEW_ANY_PERMISSIONS']);

    $this->getJson('/api/roles')->assertForbidden();
});

it('shows one role by uuid and hides the internal id', function (): void {
    apiActor(['VIEW_ROLES']);
    $role = Role::factory()->create(['name' => 'API_SHOW_ROLE']);

    $this->getJson("/api/roles/{$role->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $role->uuid)
        ->assertJsonMissingPath('data.id');
});

it('lists permissions for a token holding VIEW_ANY_PERMISSIONS', function (): void {
    apiActor(['VIEW_ANY_PERMISSIONS']);
    Permission::factory()->create(['name' => 'API_PERMISSION']);

    $this->getJson('/api/permissions?search=API_PERMISSION')
        ->assertOk()
        ->assertJsonFragment(['name' => 'API_PERMISSION']);
});

it('forbids the permission API to a token without VIEW_ANY_PERMISSIONS', function (): void {
    apiActor(['VIEW_ANY_ROLES']);

    $this->getJson('/api/permissions')->assertForbidden();
});

it('caps API pagination at 100 per page', function (): void {
    apiActor(['VIEW_ANY_PERMISSIONS']);

    $this->getJson('/api/permissions?per_page=5000')
        ->assertOk()
        ->assertJsonPath('per_page', 100);
});
