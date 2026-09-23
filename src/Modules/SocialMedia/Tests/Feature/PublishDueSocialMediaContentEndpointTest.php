<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\SocialMedia\Domain\Enums\SocialMediaContentStatus;
use Modules\SocialMedia\Infrastructure\Persistence\Eloquent\Models\SocialMediaContentEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('publishes only the content already due', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    $due = SocialMediaContentEloquentModel::factory()->create([
        'status' => SocialMediaContentStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);
    $future = SocialMediaContentEloquentModel::factory()->create([
        'status' => SocialMediaContentStatus::Scheduled,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($admin)
        ->postJson('/social-media/publish-due')
        ->assertOk()
        ->assertJsonPath('data.published', 1);

    expect($due->refresh()->status)->toBe(SocialMediaContentStatus::Published)
        ->and($future->refresh()->status)->toBe(SocialMediaContentStatus::Scheduled);
});

it('rejects guests and users without the publish permission', function (): void {
    $this->postJson('/social-media/publish-due')->assertUnauthorized();

    $plain = User::factory()->create();
    $plain->assignRole('USER');

    $this->actingAs($plain)->postJson('/social-media/publish-due')->assertForbidden();
});
