<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Role;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function roleManager(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('lets a super admin create a role with permissions', function (): void {
    $this->actingAs(roleManager())
        ->post('/roles', [
            'name' => 'CONTENT_EDITOR',
            'permissions' => ['VIEW_ROLES', 'CREATE_ROLES'],
        ])
        ->assertRedirect();

    $role = Role::query()->where('name', 'CONTENT_EDITOR')->firstOrFail();

    expect($role->hasPermissionTo('VIEW_ROLES'))->toBeTrue()
        ->and($role->hasPermissionTo('CREATE_ROLES'))->toBeTrue()
        ->and($role->uuid)->not->toBeNull();
});

it('rejects a duplicate role name', function (): void {
    $this->actingAs(roleManager())
        ->post('/roles', ['name' => 'ADMIN'])
        ->assertSessionHasErrors('name');
});

it('rejects an unknown permission name', function (): void {
    $this->actingAs(roleManager())
        ->post('/roles', ['name' => 'BROKEN', 'permissions' => ['DOES_NOT_EXIST']])
        ->assertSessionHasErrors('permissions.0');
});

it('re-syncs permissions on update', function (): void {
    $admin = roleManager();
    $this->actingAs($admin)->post('/roles', ['name' => 'REVIEWER', 'permissions' => ['VIEW_ROLES']])->assertRedirect();

    $role = Role::query()->where('name', 'REVIEWER')->firstOrFail();

    $this->actingAs($admin)
        ->put("/roles/{$role->uuid}", ['name' => 'REVIEWER', 'permissions' => ['CREATE_ROLES']])
        ->assertRedirect();

    $role->refresh();

    expect($role->hasPermissionTo('VIEW_ROLES'))->toBeFalse()
        ->and($role->hasPermissionTo('CREATE_ROLES'))->toBeTrue();
});

it('never renames a protected system role', function (): void {
    $admin = roleManager();
    $role = Role::query()->where('name', 'ADMIN')->firstOrFail();

    $this->actingAs($admin)
        ->put("/roles/{$role->uuid}", ['name' => 'ADMIN_RENAMED'])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('roles', ['uuid' => $role->uuid, 'name' => 'ADMIN']);
    $this->assertDatabaseMissing('roles', ['name' => 'ADMIN_RENAMED']);
});

it('never deletes a protected system role', function (): void {
    $admin = roleManager();
    $role = Role::query()->where('name', 'SUPER_ADMIN')->firstOrFail();

    $this->actingAs($admin)
        ->delete("/roles/{$role->uuid}")
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('roles', ['uuid' => $role->uuid, 'deleted_at' => null]);
});

it('soft-deletes then restores a role', function (): void {
    $admin = roleManager();
    $role = Role::factory()->create();

    $this->actingAs($admin)->delete("/roles/{$role->uuid}")->assertRedirect();
    $this->assertSoftDeleted('roles', ['uuid' => $role->uuid]);

    $this->actingAs($admin)->post("/roles/{$role->uuid}/restore")->assertRedirect();
    $this->assertDatabaseHas('roles', ['uuid' => $role->uuid, 'deleted_at' => null]);
});

it('bulk soft-deletes then bulk restores roles', function (): void {
    $admin = roleManager();
    $uuids = Role::factory()->count(3)->create()->pluck('uuid')->all();

    $this->actingAs($admin)->post('/roles/bulk-delete', ['uuids' => $uuids])->assertRedirect();
    foreach ($uuids as $uuid) {
        $this->assertSoftDeleted('roles', ['uuid' => $uuid]);
    }

    $this->actingAs($admin)->post('/roles/bulk-restore', ['uuids' => $uuids])->assertRedirect();
    foreach ($uuids as $uuid) {
        $this->assertDatabaseHas('roles', ['uuid' => $uuid, 'deleted_at' => null]);
    }
});

it('rejects a whole bulk batch that contains a protected role', function (): void {
    $admin = roleManager();
    $free = Role::factory()->create();
    $protected = Role::query()->where('name', 'MODERATOR')->firstOrFail();

    $this->actingAs($admin)
        ->post('/roles/bulk-delete', ['uuids' => [$free->uuid, $protected->uuid]])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('roles', ['uuid' => $free->uuid, 'deleted_at' => null]);
});

it('caps a bulk role batch at 500 uuids', function (): void {
    $uuids = array_map(static fn (): string => (string) Str::uuid7(), range(1, 501));

    $this->actingAs(roleManager())
        ->postJson('/roles/bulk-delete', ['uuids' => $uuids])
        ->assertStatus(422)
        ->assertJsonValidationErrors('uuids');
});

it('narrows the role list with the search filter', function (): void {
    Role::factory()->create(['name' => 'BILLING_MANAGER']);
    Role::factory()->create(['name' => 'WAREHOUSE_CLERK']);

    $this->actingAs(roleManager())
        ->getJson('/roles?search=BILLING')
        ->assertOk()
        ->assertJsonFragment(['name' => 'BILLING_MANAGER'])
        ->assertJsonMissing(['name' => 'WAREHOUSE_CLERK']);
});

it('narrows the role list to suspended rows', function (): void {
    $suspended = Role::factory()->suspended()->create(['name' => 'RETIRED_ROLE']);
    Role::factory()->create(['name' => 'LIVE_ROLE']);

    $this->actingAs(roleManager())
        ->getJson('/roles?status=suspended')
        ->assertOk()
        ->assertJsonFragment(['uuid' => $suspended->uuid])
        ->assertJsonMissing(['name' => 'LIVE_ROLE']);
});

it('never leaks the internal auto-increment id', function (): void {
    $role = Role::factory()->create();

    $this->actingAs(roleManager())
        ->getJson("/roles/{$role->uuid}")
        ->assertOk()
        ->assertJsonMissingPath('data.id');
});

it('forbids role management to a user without the permission', function (): void {
    $plain = User::factory()->create();
    $plain->assignRole('USER');

    $this->actingAs($plain)->get('/roles')->assertForbidden();
    $this->actingAs($plain)->post('/roles', ['name' => 'X_ROLE'])->assertForbidden();
});
