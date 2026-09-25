<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
});

function throttleAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('throttles repeated rescore runs with 429 and Retry-After', function (): void {
    $admin = throttleAdmin();

    // The route allows 2 runs per minute — the first two queue work.
    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/rescore')->assertAccepted();
    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/rescore')->assertAccepted();

    // The third run inside the window is refused, with a Retry-After hint
    // the UI mirrors as a button cooldown.
    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/rescore')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('throttles repeated discover runs with 429 and Retry-After', function (): void {
    $admin = throttleAdmin();

    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/discover')->assertAccepted();
    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/discover')->assertAccepted();

    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/discover')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('throttles ingest runs after six attempts per minute', function (): void {
    $admin = throttleAdmin();

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/ingest')->assertAccepted();
    }

    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/ingest')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('does not spend the jobs budget on ordinary group reads', function (): void {
    $admin = throttleAdmin();

    // The bandeja fires several reads on load (leads, budgets, ai-settings,
    // status). They share the group throttle but must leave the named jobs
    // buckets untouched — otherwise the first job click already answers 429.
    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->actingAs($admin)->getJson('/data/admin/lead-scout/status')->assertOk();
    }

    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/rescore')->assertAccepted();
    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/discover')->assertAccepted();
    $this->actingAs($admin)->postJson('/data/admin/lead-scout/jobs/ingest')->assertAccepted();
});
