<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditAppliedCutEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditCutDecisionEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Modules\VideoEdits\Tests\Support\FakeStorage;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->storage = new FakeStorage;
    app()->instance(StoragePort::class, $this->storage);
});

it('permanently removes the edit, its files and its rows, leaving one audit entry', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->completed()->create();
    $retained = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create(['position' => 1, 'original_name' => 'private-interview.mp4']);
    VideoEditSourceEloquentModel::factory()->purged()->for($edit, 'videoEdit')->create(['position' => 2]);
    $edit->appliedCuts()->create(['sequence' => 1, 'start_ms' => 0, 'end_ms' => 500, 'reasons' => ['manual'], 'origins' => ['user']]);
    $edit->cutDecisions()->create([
        'producer' => 'manual', 'reason' => CutReason::Manual, 'origin' => DecisionOrigin::User,
        'start_ms' => 0, 'end_ms' => 500, 'outcome' => DecisionOutcome::Applied,
    ]);
    $this->storage->seed((string) $edit->result_path, 10);
    $this->storage->seed((string) $retained->storage_path, 10);

    $this->actingAs($user)
        ->deleteJson("/data/admin/video-edits/{$edit->uuid}")
        ->assertNoContent();

    expect(VideoEditEloquentModel::query()->count())->toBe(0)
        ->and(VideoEditSourceEloquentModel::query()->count())->toBe(0)
        ->and(VideoEditCutDecisionEloquentModel::query()->count())->toBe(0)
        ->and(VideoEditAppliedCutEloquentModel::query()->count())->toBe(0)
        ->and($this->storage->objects)->toBe([])
        ->and($this->storage->deleted)->toEqualCanonicalizing([$edit->result_path, $retained->storage_path]);

    $audit = Activity::query()->where('description', 'video_edit.deleted')->sole();

    expect($audit->properties->all())->toBe([
        'edit_uuid' => $edit->uuid,
        'mode' => 'auto_edit',
        'status_at_deletion' => 'completed',
        'source_count' => 2,
    ])
        ->and($audit->causer_id)->toBe($user->id)
        ->and(json_encode($audit->properties))->not->toContain('private-interview')
        ->and(json_encode($audit->properties))->not->toContain('video-edits/');
});

it('deletes queued, failed and draft edits', function (string $state): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->{$state}()->create();

    $this->actingAs($user)->deleteJson("/data/admin/video-edits/{$edit->uuid}")->assertNoContent();

    expect(VideoEditEloquentModel::query()->count())->toBe(0);
})->with(['queued', 'failed', 'draft' => 'merge']);

it('refuses to delete an edit while it is processing', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->processing()->create();
    $source = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create();
    $this->storage->seed((string) $source->storage_path, 10);

    $this->actingAs($user)
        ->deleteJson("/data/admin/video-edits/{$edit->uuid}")
        ->assertConflict()
        ->assertJsonPath('code', 'invalid_state');

    expect(VideoEditEloquentModel::query()->count())->toBe(1)
        ->and($this->storage->deleted)->toBe([])
        ->and(Activity::query()->where('description', 'video_edit.deleted')->exists())->toBeFalse();
});

it('keeps everything when a file cannot be deleted', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->completed()->create();
    $storage = Mockery::mock(StoragePort::class);
    $storage->shouldReceive('delete')->andThrow(new RuntimeException('R2 unavailable'));
    app()->instance(StoragePort::class, $storage);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($user)->deleteJson("/data/admin/video-edits/{$edit->uuid}"))
        ->toThrow(RuntimeException::class);

    expect(VideoEditEloquentModel::query()->count())->toBe(1);
});

it('hides other users\' edits', function (): void {
    $edit = VideoEditEloquentModel::factory()->completed()->create();

    $this->actingAs(VideoEditTestUsers::editor())
        ->deleteJson("/data/admin/video-edits/{$edit->uuid}")
        ->assertNotFound();

    expect(VideoEditEloquentModel::query()->count())->toBe(1);
});
