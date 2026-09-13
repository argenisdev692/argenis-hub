<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditAppliedCutEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditCutDecisionEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;

uses(RefreshDatabase::class);

it('persists an edit with sources, decisions and applied cuts', function (): void {
    $edit = VideoEditEloquentModel::factory()->completed()->create();

    VideoEditSourceEloquentModel::factory()->probed()->for($edit, 'videoEdit')->create(['position' => 2]);
    VideoEditSourceEloquentModel::factory()->probed()->for($edit, 'videoEdit')->create(['position' => 1]);

    $edit->cutDecisions()->create([
        'producer' => 'silence_detector',
        'reason' => CutReason::Silence,
        'origin' => DecisionOrigin::SystemDetection,
        'start_ms' => 1_000,
        'end_ms' => 2_500,
        'outcome' => DecisionOutcome::Applied,
        'applied_cut_sequence' => 1,
    ]);
    $edit->cutDecisions()->create([
        'producer' => 'manual',
        'reason' => CutReason::Manual,
        'origin' => DecisionOrigin::User,
        'start_ms' => 900_000,
        'end_ms' => 901_000,
        'confidence' => '0.950',
        'evidence' => ['note' => 'retake'],
        'outcome' => DecisionOutcome::Rejected,
        'rejection_reason' => 'end_beyond_duration',
    ]);
    $edit->appliedCuts()->create([
        'sequence' => 1,
        'start_ms' => 1_000,
        'end_ms' => 2_500,
        'reasons' => ['silence'],
        'origins' => ['system_detection'],
    ]);

    $fresh = VideoEditEloquentModel::query()
        ->with(['sources', 'cutDecisions', 'appliedCuts', 'user'])
        ->findOrFail($edit->id);

    expect($fresh->status)->toBe(VideoEditStatus::Completed)
        ->and($fresh->mode)->toBe(VideoEditMode::AutoEdit)
        ->and($fresh->parameters['silence_removal']['threshold_seconds'])->toEqual(1.0)
        ->and($fresh->uuid)->toBeString()->not->toBeEmpty()
        ->and($fresh->sources->sortBy('position')->pluck('position')->values()->all())->toBe([1, 2])
        ->and($fresh->cutDecisions)->toHaveCount(2)
        ->and($fresh->cutDecisions->last()->outcome)->toBe(DecisionOutcome::Rejected)
        ->and($fresh->cutDecisions->last()->evidence)->toBe(['note' => 'retake'])
        ->and($fresh->appliedCuts->first()->reasons)->toBe(['silence'])
        ->and($fresh->user->videoEdits()->count())->toBe(1);
});

it('never serializes internal keys or object paths', function (): void {
    $edit = VideoEditEloquentModel::factory()->completed()->create();
    $source = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create();

    expect($edit->toArray())->not->toHaveKeys(['id', 'user_id', 'previous_edit_id', 'result_path'])
        ->and($source->toArray())->not->toHaveKeys(['id', 'video_edit_id', 'storage_path']);
});

it('allows only one queued or processing edit per user', function (): void {
    $user = User::factory()->create();

    VideoEditEloquentModel::factory()->for($user)->completed()->create();
    VideoEditEloquentModel::factory()->for($user)->failed()->create();
    VideoEditEloquentModel::factory()->for($user)->create(); // draft
    VideoEditEloquentModel::factory()->for($user)->queued()->create();

    expect(fn () => VideoEditEloquentModel::factory()->for($user)->processing()->create())
        ->toThrow(QueryException::class);

    VideoEditEloquentModel::factory()->queued()->create(); // another user is unaffected

    expect(VideoEditEloquentModel::query()->whereIn('status', VideoEditStatus::activeValues())->count())->toBe(2);
});

it('removes every child row when the edit is hard-deleted', function (): void {
    $edit = VideoEditEloquentModel::factory()->completed()->create();
    VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create();
    $edit->appliedCuts()->create([
        'sequence' => 1, 'start_ms' => 0, 'end_ms' => 500, 'reasons' => ['manual'], 'origins' => ['user'],
    ]);
    $edit->cutDecisions()->create([
        'producer' => 'manual', 'reason' => CutReason::Manual, 'origin' => DecisionOrigin::User,
        'start_ms' => 0, 'end_ms' => 500, 'outcome' => DecisionOutcome::Applied,
    ]);

    $edit->delete();

    expect(VideoEditEloquentModel::query()->count())->toBe(0)
        ->and(VideoEditSourceEloquentModel::query()->count())->toBe(0)
        ->and(VideoEditCutDecisionEloquentModel::query()->count())->toBe(0)
        ->and(VideoEditAppliedCutEloquentModel::query()->count())->toBe(0);
});

it('links an edit and its re-edits in both directions', function (): void {
    $original = VideoEditEloquentModel::factory()->completed()->create();
    $reEdit = VideoEditEloquentModel::factory()->for($original->user)->create(['previous_edit_id' => $original->id]);

    expect($original->reEdits()->pluck('uuid')->all())->toBe([$reEdit->uuid])
        ->and($reEdit->previousEdit?->uuid)->toBe($original->uuid)
        ->and($original->user->videoEdits()->count())->toBe(2);
});

it('keeps a re-edit when its original is deleted', function (): void {
    $original = VideoEditEloquentModel::factory()->completed()->create();
    $reEdit = VideoEditEloquentModel::factory()->for($original->user)->create(['previous_edit_id' => $original->id]);

    $original->delete();

    expect($reEdit->fresh()?->previous_edit_id)->toBeNull();
});
