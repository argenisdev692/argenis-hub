<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\LeadScoutSourcesSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\LeadScout\Domain\Exceptions\SuppressedException;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutCompanyEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutOutreachStageEventEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSourceEloquentModel;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutSuppressionEloquentModel;
use Modules\LeadScout\Infrastructure\Queue\IngestSourceJob;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
});

function sourcesAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

it('seeds six paused sources with apis ranked above rss', function (): void {
    $this->seed(LeadScoutSourcesSeeder::class);

    $sources = ScoutSourceEloquentModel::query()->get();

    expect($sources)->toHaveCount(6)
        ->and($sources->every(fn ($s): bool => $s->status->value === 'paused' && $s->terms_reviewed_at === null))->toBeTrue()
        ->and($sources->where('access_method', 'api')->min('priority'))->toBeGreaterThan($sources->where('access_method', 'rss')->max('priority'))
        ->and($sources->firstWhere('name', 'Remotive')->frequency_minutes)->toBeGreaterThanOrEqual(360);
});

it('lists sources and rejects activation without reviewed terms', function (): void {
    $admin = sourcesAdmin();
    $this->seed(LeadScoutSourcesSeeder::class);
    $uuid = ScoutSourceEloquentModel::query()->where('name', 'LaraJobs')->value('uuid');

    $this->actingAs($admin)->getJson('/data/admin/lead-scout/sources')->assertOk()->assertJsonCount(6, 'data');

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/sources/{$uuid}", ['status' => 'active'])
        ->assertUnprocessable()
        ->assertJson(['code' => 'TERMS_NOT_REVIEWED']);

    $this->actingAs($admin)
        ->patchJson("/data/admin/lead-scout/sources/{$uuid}", ['status' => 'active', 'terms_reviewed_at' => now()->toIso8601String()])
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('queues a run and answers 409 while one is in flight', function (): void {
    $admin = sourcesAdmin();
    $this->seed(LeadScoutSourcesSeeder::class);
    $uuid = ScoutSourceEloquentModel::query()->where('name', 'LaraJobs')->value('uuid');

    $this->actingAs($admin)->postJson("/data/admin/lead-scout/sources/{$uuid}/run")->assertAccepted();
    Queue::assertPushed(IngestSourceJob::class);

    cache()->put(IngestSourceJob::runningKey($uuid), true, now()->addMinutes(5));

    $this->actingAs($admin)
        ->postJson("/data/admin/lead-scout/sources/{$uuid}/run")
        ->assertConflict()
        ->assertJson(['code' => 'SOURCE_RUNNING']);
});

it('creates a manual lead and rejects suppressed or invalid urls', function (): void {
    $admin = sourcesAdmin();

    $response = $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/leads', [
            'name' => 'Nébula Labs',
            'url' => 'https://www.nebula-labs.pt/contacto',
            'note' => 'From the ICP list',
        ])
        ->assertCreated();

    expect($response->json('data.domain'))->toBe('nebula-labs.pt')
        ->and($response->json('data.origin'))->toBe('manual');

    $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/leads', ['name' => 'x', 'url' => 'not-a-url'])
        ->assertUnprocessable();

    ScoutSuppressionEloquentModel::query()->create([
        'canonical_domain' => 'nebula-labs.pt',
        'source' => 'manual',
        'reason' => 'Do not contact',
    ]);

    $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/leads', ['name' => 'Nébula Labs', 'url' => 'https://nebula-labs.pt'])
        ->assertConflict()
        ->assertJson(['code' => SuppressedException::CODE]);
});

it('creates suppressions idempotently', function (): void {
    $admin = sourcesAdmin();

    $first = $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/suppressions', ['domain' => 'WWW.Acme.ES', 'reason' => 'Asked twice'])
        ->assertCreated()
        ->json('data');

    $second = $this->actingAs($admin)
        ->postJson('/data/admin/lead-scout/suppressions', ['domain' => 'acme.es', 'reason' => 'Asked twice'])
        ->assertCreated()
        ->json('data');

    expect($first['uuid'])->toBe($second['uuid'])
        ->and(ScoutSuppressionEloquentModel::query()->where('canonical_domain', 'acme.es')->count())->toBe(1);
});

it('imports an icp csv with history and reports bad rows', function (): void {
    $admin = sourcesAdmin();
    $path = tempnam(sys_get_temp_dir(), 'icp').'.csv';
    file_put_contents($path, implode("\n", [
        'Nébula Labs,https://nebula-labs.pt,ICP row',
        'Nébula Labs,https://nebula-labs.pt,duplicate row',
        'Broken Row,not-a-url,oops',
        'Suppressed Co,https://suppressed.example,do not call',
        'Prior Contact,https://prior.example,phase zero,2026-09-01 10:00:00,contact_form,vacancy,sent',
    ]));

    ScoutSuppressionEloquentModel::query()->create([
        'canonical_domain' => 'suppressed.example',
        'source' => 'manual',
        'reason' => 'DNC',
    ]);

    $this->artisan('lead-scout:import-leads', ['file' => $path])->assertSuccessful();

    expect(ScoutCompanyEloquentModel::query()->where('origin', 'manual')->count())->toBe(2)
        ->and(ScoutOutreachEloquentModel::query()->count())->toBe(1)
        ->and(ScoutOutreachStageEventEloquentModel::query()->count())->toBe(1);

    unlink($path);
});
