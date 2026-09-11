<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Modules\VideoEdits\Infrastructure\Queue\ProcessVideoEditJob;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
});

it('re-queues a failed edit with its retained sources', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->failed()->create();
    VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create();

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/retry")
        ->assertAccepted()
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('failure', null)
        ->assertJsonPath('progress_percent', 0);

    $fresh = $edit->fresh();

    expect($fresh->status)->toBe(VideoEditStatus::Queued)
        ->and($fresh->sources_expire_at)->toBeNull()
        ->and($fresh->failed_at)->toBeNull();

    Queue::assertPushedOn('video-edits', ProcessVideoEditJob::class, fn (ProcessVideoEditJob $job): bool => $job->videoEditUuid === $edit->uuid);
    expect(Activity::query()->where('description', 'video_edit.retried')->sole()->properties->all())
        ->toBe(['edit_uuid' => $edit->uuid, 'ranges_corrected' => false]);
});

it('accepts corrected ranges after a range failure and keeps the other settings', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->invalidCutRanges()->create([
        'parameters' => [
            'silence_removal' => ['enabled' => true, 'threshold_seconds' => 1.5],
            'manual_ranges' => [['start_ms' => 70_000, 'end_ms' => 71_000, 'note' => null]],
        ],
    ]);
    VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create();

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/retry", [
            'manual_ranges' => [['start_ms' => 50_000, 'end_ms' => 51_000, 'note' => ' fixed ']],
        ])
        ->assertAccepted()
        ->assertJsonPath('parameters.manual_ranges', [['start_ms' => 50_000, 'end_ms' => 51_000, 'note' => 'fixed']])
        ->assertJsonPath('parameters.silence_removal.threshold_seconds', 1.5);

    expect(Activity::query()->where('description', 'video_edit.retried')->sole()->properties['ranges_corrected'])->toBeTrue();
});

it('only accepts corrected ranges when the ranges caused the failure', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->failed()->create();
    VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create();

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/retry", [
            'manual_ranges' => [['start_ms' => 0, 'end_ms' => 1_000]],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'ranges_not_correctable');

    expect($edit->fresh()->status)->toBe(VideoEditStatus::Failed);
    Queue::assertNotPushed(ProcessVideoEditJob::class);
});

it('validates corrected ranges like a new edit', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->invalidCutRanges()->create();

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/retry", [
            'manual_ranges' => [['start_ms' => 5_000, 'end_ms' => 4_000]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('manual_ranges.0.end_ms');
});

it('refuses retries once the sources are gone or the edit did not fail', function (string $state, bool $withRetainedSource): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->{$state}()->create();
    $source = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit');
    ($withRetainedSource ? $source : $source->purged())->create();

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/retry")
        ->assertConflict()
        ->assertJsonPath('code', 'not_retryable');

    Queue::assertNotPushed(ProcessVideoEditJob::class);
})->with([
    'retry window expired' => ['failedWithExpiredSources', true],
    'source already purged' => ['failed', false],
    'completed edit' => ['completed', true],
    'still processing' => ['processing', true],
]);

it('refuses a retry while another edit is active', function (): void {
    $user = VideoEditTestUsers::editor();
    VideoEditEloquentModel::factory()->for($user)->queued()->create();
    $edit = VideoEditEloquentModel::factory()->for($user)->failed()->create();
    VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create();

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/retry")
        ->assertConflict()
        ->assertJsonPath('code', 'already_active');
});

it('hides other users\' edits', function (): void {
    $edit = VideoEditEloquentModel::factory()->failed()->create();

    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson("/data/admin/video-edits/{$edit->uuid}/retry")
        ->assertNotFound();
});
