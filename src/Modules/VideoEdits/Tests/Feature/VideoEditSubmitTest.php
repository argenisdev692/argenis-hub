<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Modules\VideoEdits\Infrastructure\Queue\ProcessVideoEditJob;
use Modules\VideoEdits\Tests\Support\FakeStorage;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Queue::fake();
    $this->storage = new FakeStorage;
    app()->instance(StoragePort::class, $this->storage);
});

function submitter(): User
{
    $user = User::factory()->create();
    $user->assignRole('SUPER_ADMIN');

    return $user;
}

/**
 * A draft with two sources whose declared sizes are 1 000 and 2 000 bytes.
 */
function draftFor(User $user): VideoEditEloquentModel
{
    $edit = VideoEditEloquentModel::factory()->for($user)->create();

    VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create(['position' => 1, 'declared_size_bytes' => 1_000]);
    VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create(['position' => 2, 'declared_size_bytes' => 2_000]);

    return $edit->load('sources');
}

it('verifies the uploads, queues the edit and audits the submission', function (): void {
    $user = submitter();
    $edit = draftFor($user);
    $this->storage->seed($edit->sources[0]->storage_path, 1_000);
    $this->storage->seed($edit->sources[1]->storage_path, 2_005);

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/submit")
        ->assertAccepted()
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('progress_percent', 0);

    $fresh = $edit->fresh(['sources']);

    expect($fresh->status)->toBe(VideoEditStatus::Queued)
        ->and($fresh->queued_at)->not->toBeNull()
        ->and($fresh->sources->pluck('size_bytes')->all())->toBe([1_000, 2_005]);

    Queue::assertPushedOn('video-edits', ProcessVideoEditJob::class, fn (ProcessVideoEditJob $job): bool => $job->videoEditUuid === $edit->uuid);

    $audit = Activity::query()->where('description', 'video_edit.submitted')->sole();
    expect($audit->properties->all())->toBe(['edit_uuid' => $edit->uuid, 'mode' => 'auto_edit', 'source_count' => 2])
        ->and($audit->causer_id)->toBe($user->id);
});

it('reports every source that was not uploaded correctly and keeps the draft', function (): void {
    config()->set('video-edit.limits.max_file_bytes', 1_500);
    $user = submitter();
    $edit = VideoEditEloquentModel::factory()->for($user)->create();
    $missing = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create(['position' => 1, 'declared_size_bytes' => 1_000]);
    $tooLarge = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create(['position' => 2, 'declared_size_bytes' => 1_000]);
    $mismatch = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create(['position' => 3, 'declared_size_bytes' => 1_000]);
    $this->storage->seed($tooLarge->storage_path, 1_600);
    $this->storage->seed($mismatch->storage_path, 900);

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/submit")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_sources')
        ->assertJsonPath("errors.sources.{$missing->uuid}", 'source_missing')
        ->assertJsonPath("errors.sources.{$tooLarge->uuid}", 'source_too_large')
        ->assertJsonPath("errors.sources.{$mismatch->uuid}", 'source_size_mismatch');

    expect($edit->fresh()->status)->toBe(VideoEditStatus::Draft);
    Queue::assertNotPushed(ProcessVideoEditJob::class);
});

it('refuses a second active edit for the same user', function (): void {
    $user = submitter();
    VideoEditEloquentModel::factory()->for($user)->processing()->create();
    $edit = draftFor($user);
    $this->storage->seed($edit->sources[0]->storage_path, 1_000);
    $this->storage->seed($edit->sources[1]->storage_path, 2_000);

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/submit")
        ->assertConflict()
        ->assertJsonPath('code', 'already_active');

    expect($edit->fresh()->status)->toBe(VideoEditStatus::Draft);
    Queue::assertNotPushed(ProcessVideoEditJob::class);
});

it('only submits drafts', function (): void {
    $user = submitter();
    $edit = VideoEditEloquentModel::factory()->for($user)->completed()->create();

    $this->actingAs($user)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/submit")
        ->assertConflict()
        ->assertJsonPath('code', 'invalid_state');
});

it('hides other users\' drafts', function (): void {
    $edit = draftFor(User::factory()->create());

    $this->actingAs(submitter())
        ->postJson("/data/admin/video-edits/{$edit->uuid}/submit")
        ->assertNotFound();

    Queue::assertNotPushed(ProcessVideoEditJob::class);
});
