<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * A SUPER_ADMIN holds every permission, including the read-only
 * `*_ACTIVITY_LOGS` set.
 */
function activityLogAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('SUPER_ADMIN');

    return $admin;
}

function recordActivity(string $description, ?DateTimeInterface $createdAt = null): Activity
{
    /** @var Activity $activity */
    $activity = activity()->event('created')->log($description);

    if ($createdAt !== null) {
        $activity->forceFill(['created_at' => $createdAt])->save();
    }

    return $activity;
}

it('lists activity-log entries for an authorized user', function (): void {
    $activity = recordActivity('did a thing', now()->addSecond());

    $this->actingAs(activityLogAdmin())
        ->getJson('/activity-logs?search='.urlencode('did a thing'))
        ->assertOk()
        ->assertJsonFragment(['id' => $activity->id, 'event' => 'created']);
});

it('shows a single activity-log entry with its payloads', function (): void {
    $activity = recordActivity('did another thing');

    $this->actingAs(activityLogAdmin())
        ->getJson("/activity-logs/{$activity->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $activity->id)
        ->assertJsonPath('data.description', 'did another thing');
});

it('forbids users without permission from viewing the trail', function (): void {
    recordActivity('sensitive');

    $this->actingAs(User::factory()->create())
        ->getJson('/activity-logs')
        ->assertForbidden();
});

it('filters the trail by an inclusive date range', function (): void {
    $inside = recordActivity('inside the window', now()->subDays(2));
    $outside = recordActivity('outside the window', now()->subDays(20));

    $this->actingAs(activityLogAdmin())
        ->getJson('/activity-logs?date_from='.now()->subDays(5)->toDateString().'&date_to='.now()->toDateString())
        ->assertOk()
        ->assertJsonFragment(['id' => $inside->id])
        ->assertJsonMissing(['id' => $outside->id]);
});

it('exports the trail as csv', function (): void {
    recordActivity('exported thing');

    $response = $this->actingAs(activityLogAdmin())->get('/activity-logs/export?format=csv');

    $response->assertOk();
    expect((string) $response->headers->get('content-disposition'))->toContain('activity-logs.csv');
});

it('exports the trail as xlsx', function (): void {
    recordActivity('exported thing');

    $this->actingAs(activityLogAdmin())
        ->get('/activity-logs/export?format=xlsx')
        ->assertOk();
});

it('exports the trail as pdf', function (): void {
    recordActivity('exported thing');

    $this->actingAs(activityLogAdmin())
        ->get('/activity-logs/export?format=pdf')
        ->assertOk();
});

it('rejects an unsupported export format', function (): void {
    $this->actingAs(activityLogAdmin())
        ->get('/activity-logs/export?format=exe')
        ->assertStatus(422);
});

it('serves the trail to sanctum clients that hold the permission', function (): void {
    $activity = recordActivity('api thing');

    $this->actingAs(activityLogAdmin(), 'sanctum')
        ->getJson('/api/activity-logs')
        ->assertOk()
        ->assertJsonFragment(['id' => $activity->id]);

    $this->actingAs(activityLogAdmin(), 'sanctum')
        ->getJson("/api/activity-logs/{$activity->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $activity->id);
});

it('denies sanctum clients without the permission', function (): void {
    recordActivity('api thing');

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->getJson('/api/activity-logs')
        ->assertForbidden();
});

it('archives rows past the hot window and keeps recent ones', function (): void {
    Storage::fake(config('filesystems.cloud'));

    $old = recordActivity('ancient', now()->subDays(120));
    $recent = recordActivity('fresh');

    $this->artisan('activity-log:archive', ['--days' => 90])->assertSuccessful();

    $this->assertDatabaseMissing('activity_log', ['id' => $old->id]);
    $this->assertDatabaseHas('activity_log', ['id' => $recent->id]);
    $this->assertDatabaseHas('activity_log', ['event' => 'activity_log.archived']);
    expect(Storage::disk(config('filesystems.cloud'))->allFiles('archives/activity-log'))->not->toBeEmpty();
});

it('archives nothing on a dry run', function (): void {
    Storage::fake(config('filesystems.cloud'));

    $old = recordActivity('ancient', now()->subDays(120));

    $this->artisan('activity-log:archive', ['--days' => 90, '--dry-run' => true])->assertSuccessful();

    $this->assertDatabaseHas('activity_log', ['id' => $old->id]);
    expect(Storage::disk(config('filesystems.cloud'))->allFiles('archives/activity-log'))->toBeEmpty();
});
