<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Campaigns\Domain\Enums\CampaignStatus;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('publishes only the campaigns already due', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    $due = CampaignEloquentModel::factory()->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_at' => now()->subMinute(),
    ]);
    $future = CampaignEloquentModel::factory()->create([
        'status' => CampaignStatus::Scheduled,
        'scheduled_at' => now()->addDay(),
    ]);

    $this->actingAs($admin)
        ->postJson('/campaigns/publish-due')
        ->assertOk()
        ->assertJsonPath('data.published', 1);

    expect($due->refresh()->status)->toBe(CampaignStatus::Published)
        ->and($future->refresh()->status)->toBe(CampaignStatus::Scheduled);
});

it('rejects guests and users without the publish permission', function (): void {
    $this->postJson('/campaigns/publish-due')->assertUnauthorized();

    $plain = User::factory()->create();
    $plain->assignRole('USER');

    $this->actingAs($plain)->postJson('/campaigns/publish-due')->assertForbidden();
});

it('schedules the publish pass every minute', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'campaigns:publish-scheduled'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('* * * * *');
});
