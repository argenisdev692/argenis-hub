<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Authorization\Infrastructure\Persistence\Eloquent\Models\Permission;
use Modules\LeadScout\Providers\LeadScoutServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('boots the provider and exposes the status route', function (): void {
    expect(app()->getProvider(LeadScoutServiceProvider::class))->not->toBeNull();

    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    $this->actingAs($admin)
        ->getJson('/data/admin/lead-scout/status')
        ->assertOk()
        ->assertJson(['module' => 'lead-scout', 'rules_version' => '2026.09.3', 'ok' => true]);
});

it('seeds the six lead-scout permissions onto admin', function (): void {
    foreach (['VIEW_ANY', 'VIEW', 'CREATE', 'UPDATE', 'DELETE', 'EXPORT'] as $action) {
        expect(Permission::query()
            ->where('name', "{$action}_LEAD_SCOUT")->exists())->toBeTrue();
    }

    $admin = User::factory()->create();
    $admin->assignRole('ADMIN');

    expect($admin->can('VIEW_ANY_LEAD_SCOUT'))->toBeTrue()
        ->and($admin->can('EXPORT_LEAD_SCOUT'))->toBeTrue();
});

it('denies the status route without permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('GUEST');

    $this->actingAs($user)->getJson('/data/admin/lead-scout/status')->assertForbidden();
});
