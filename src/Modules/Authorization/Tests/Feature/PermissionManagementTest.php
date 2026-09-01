<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function permissionManager(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('lets a super admin create a permission', function (): void {
    $this->actingAs(permissionManager())
        ->post('/permissions', ['name' => 'VIEW_ANY_INVOICES'])
        ->assertRedirect();

    $this->assertDatabaseHas('permissions', ['name' => 'VIEW_ANY_INVOICES', 'guard_name' => 'web']);
});

it('rejects a duplicate permission name', function (): void {
    $this->actingAs(permissionManager())
        ->post('/permissions', ['name' => 'VIEW_ROLES'])
        ->assertSessionHasErrors('name');
});

it('rejects a lower case permission name', function (): void {
    $this->actingAs(permissionManager())
        ->post('/permissions', ['name' => 'view_invoices'])
        ->assertSessionHasErrors('name');
});

it('renames a permission', function (): void {
    $admin = permissionManager();
    $permission = Permission::factory()->create();

    $this->actingAs($admin)
        ->put("/permissions/{$permission->uuid}", ['name' => 'RENAMED_PERMISSION'])
        ->assertRedirect();

    $this->assertDatabaseHas('permissions', ['uuid' => $permission->uuid, 'name' => 'RENAMED_PERMISSION']);
});

it('soft-deletes then restores a permission', function (): void {
    $admin = permissionManager();
    $permission = Permission::factory()->create();

    $this->actingAs($admin)->delete("/permissions/{$permission->uuid}")->assertRedirect();
    $this->assertSoftDeleted('permissions', ['uuid' => $permission->uuid]);

    $this->actingAs($admin)->post("/permissions/{$permission->uuid}/restore")->assertRedirect();
    $this->assertDatabaseHas('permissions', ['uuid' => $permission->uuid, 'deleted_at' => null]);
});

it('bulk soft-deletes then bulk restores permissions', function (): void {
    $admin = permissionManager();
    $uuids = Permission::factory()->count(3)->create()->pluck('uuid')->all();

    $this->actingAs($admin)->post('/permissions/bulk-delete', ['uuids' => $uuids])->assertRedirect();
    foreach ($uuids as $uuid) {
        $this->assertSoftDeleted('permissions', ['uuid' => $uuid]);
    }

    $this->actingAs($admin)->post('/permissions/bulk-restore', ['uuids' => $uuids])->assertRedirect();
    foreach ($uuids as $uuid) {
        $this->assertDatabaseHas('permissions', ['uuid' => $uuid, 'deleted_at' => null]);
    }
});

it('caps a bulk permission batch at 500 uuids', function (): void {
    $uuids = array_map(static fn (): string => (string) Str::uuid7(), range(1, 501));

    $this->actingAs(permissionManager())
        ->postJson('/permissions/bulk-delete', ['uuids' => $uuids])
        ->assertStatus(422)
        ->assertJsonValidationErrors('uuids');
});

it('narrows the permission list with the search filter', function (): void {
    Permission::factory()->create(['name' => 'MANAGE_WIDGETS']);
    Permission::factory()->create(['name' => 'MANAGE_GADGETS']);

    $this->actingAs(permissionManager())
        ->getJson('/permissions?search=WIDGETS')
        ->assertOk()
        ->assertJsonFragment(['name' => 'MANAGE_WIDGETS'])
        ->assertJsonMissing(['name' => 'MANAGE_GADGETS']);
});

it('narrows the permission list to suspended rows', function (): void {
    $suspended = Permission::factory()->suspended()->create(['name' => 'RETIRED_PERMISSION']);
    Permission::factory()->create(['name' => 'LIVE_PERMISSION']);

    $this->actingAs(permissionManager())
        ->getJson('/permissions?status=suspended')
        ->assertOk()
        ->assertJsonFragment(['uuid' => $suspended->uuid])
        ->assertJsonMissing(['name' => 'LIVE_PERMISSION']);
});

it('rejects a date range whose start is after its end', function (): void {
    $this->actingAs(permissionManager())
        ->getJson('/permissions?date_from=2026-05-10&date_to=2026-05-01')
        ->assertStatus(422);
});

it('never leaks the internal auto-increment id', function (): void {
    $permission = Permission::factory()->create();

    $this->actingAs(permissionManager())
        ->getJson("/permissions/{$permission->uuid}")
        ->assertOk()
        ->assertJsonMissingPath('data.id');
});

it('forbids permission management to a user without the permission', function (): void {
    $plain = User::factory()->create();
    $plain->assignRole('USER');

    $this->actingAs($plain)->get('/permissions')->assertForbidden();
    $this->actingAs($plain)->post('/permissions', ['name' => 'HACK_PERMISSION'])->assertForbidden();
});
