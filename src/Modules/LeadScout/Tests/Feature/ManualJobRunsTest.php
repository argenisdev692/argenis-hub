<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\ScoutCompanyFactory;
use Database\Factories\ScoutSignalFactory;
use Database\Factories\ScoutSourceFactory;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\LeadScout\Infrastructure\Queue\DiscoverAgenciesJob;
use Modules\LeadScout\Infrastructure\Queue\IngestSourceJob;
use Modules\LeadScout\Infrastructure\Queue\ScoreCompanyJob;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
});

function jobsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('queues every active source that is not already running', function (): void {
    $running = ScoutSourceFactory::new()->active()->create();
    ScoutSourceFactory::new()->active()->create();
    ScoutSourceFactory::new()->create();

    cache()->put(IngestSourceJob::runningKey($running->uuid), true, now()->addMinutes(5));

    $this->actingAs(jobsAdmin())
        ->postJson('/data/admin/lead-scout/jobs/ingest')
        ->assertAccepted()
        ->assertJsonPath('data.queued', 1);

    Queue::assertPushedTimes(IngestSourceJob::class, 1);
    Queue::assertNotPushed(IngestSourceJob::class, fn (IngestSourceJob $job): bool => $job->sourceUuid === $running->uuid);
});

it('queues a discovery run', function (): void {
    $this->actingAs(jobsAdmin())
        ->postJson('/data/admin/lead-scout/jobs/discover')
        ->assertAccepted();

    Queue::assertPushedOnce(DiscoverAgenciesJob::class);
});

it('queues a re-score only for companies with signals', function (): void {
    $scored = ScoutCompanyFactory::new()->create();
    ScoutSignalFactory::new()->create(['company_id' => $scored->id]);
    ScoutCompanyFactory::new()->create();

    $this->actingAs(jobsAdmin())
        ->postJson('/data/admin/lead-scout/jobs/rescore')
        ->assertAccepted()
        ->assertJsonPath('data.queued', 1);

    Queue::assertPushed(ScoreCompanyJob::class, fn (ScoreCompanyJob $job): bool => $job->companyUuid === $scored->uuid);
    Queue::assertPushedTimes(ScoreCompanyJob::class, 1);
});

it('rejects guests and users without the update permission', function (string $job): void {
    $this->postJson("/data/admin/lead-scout/jobs/{$job}")->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson("/data/admin/lead-scout/jobs/{$job}")
        ->assertForbidden();

    Queue::assertNotPushed(IngestSourceJob::class);
    Queue::assertNotPushed(DiscoverAgenciesJob::class);
    Queue::assertNotPushed(ScoreCompanyJob::class);
})->with(['ingest', 'discover', 'rescore']);
