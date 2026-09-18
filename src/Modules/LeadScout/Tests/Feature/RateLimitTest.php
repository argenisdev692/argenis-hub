<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function rateLimitAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('returns 429 on the export throttle (T075)', function (): void {
    config()->set('lead-scout.rate_limits.export', 1);
    $admin = rateLimitAdmin();

    $this->actingAs($admin)->get('/data/admin/lead-scout/leads/export?dataset=leads&format=csv')->assertOk();
    $this->actingAs($admin)->get('/data/admin/lead-scout/leads/export?dataset=leads&format=csv')->assertStatus(429);
});

it('registers the llm and export limiters (T075)', function (): void {
    expect(
        RateLimiter::limiter('lead-scout-llm'),
    )->not->toBeNull()
        ->and(RateLimiter::limiter('lead-scout-export'))->not->toBeNull();
});
