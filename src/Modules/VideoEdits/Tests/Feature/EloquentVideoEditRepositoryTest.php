<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\Exceptions\VideoEditStateConflictException;
use Modules\VideoEdits\Domain\Ports\VideoEditRepositoryPort;
use Modules\VideoEdits\Domain\Services\CutPlanner;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->repository = app(VideoEditRepositoryPort::class);
});

it('moves an edit only when it is still in an expected status', function (): void {
    $edit = VideoEditEloquentModel::factory()->create(); // draft

    $moved = $this->repository->transitionStatus($edit->uuid, [VideoEditStatus::Draft], VideoEditStatus::Queued, [
        'queued_at' => now(),
    ]);
    $movedAgain = $this->repository->transitionStatus($edit->uuid, [VideoEditStatus::Draft], VideoEditStatus::Queued);

    expect($moved)->toBeTrue()
        ->and($movedAgain)->toBeFalse()
        ->and($edit->fresh()->status)->toBe(VideoEditStatus::Queued)
        ->and($edit->fresh()->queued_at)->not->toBeNull();
});

it('refuses transitions the lifecycle does not allow', function (): void {
    $edit = VideoEditEloquentModel::factory()->completed()->create();

    $this->repository->transitionStatus($edit->uuid, [VideoEditStatus::Completed], VideoEditStatus::Queued);
})->throws(InvalidArgumentException::class);

it('turns a second active edit into a state conflict', function (): void {
    $user = User::factory()->create();
    VideoEditEloquentModel::factory()->for($user)->processing()->create();
    $draft = VideoEditEloquentModel::factory()->for($user)->create();

    try {
        $this->repository->transitionStatus($draft->uuid, [VideoEditStatus::Draft], VideoEditStatus::Queued);
    } catch (VideoEditStateConflictException $exception) {
        expect($exception->reasonCode)->toBe('already_active')
            ->and($draft->fresh()->status)->toBe(VideoEditStatus::Draft);

        return;
    }

    throw new RuntimeException('Expected a state conflict.');
});

it('encodes casted attributes written during a transition', function (): void {
    $edit = VideoEditEloquentModel::factory()->processing()->create();

    $this->repository->transitionStatus($edit->uuid, [VideoEditStatus::Processing], VideoEditStatus::Failed, [
        'failure_code' => 'invalid_cut_ranges',
        'failure_details' => ['manual_ranges' => [['index' => 2, 'error' => 'end_beyond_duration']]],
        'current_stage' => ProcessingStage::Download,
        'failed_at' => now(),
    ]);

    $fresh = $edit->fresh();

    expect($fresh->status)->toBe(VideoEditStatus::Failed)
        ->and($fresh->failure_details)->toBe(['manual_ranges' => [['index' => 2, 'error' => 'end_beyond_duration']]])
        ->and($fresh->current_stage)->toBe(ProcessingStage::Download);
});

it('records progress only while the edit is processing', function (): void {
    $processing = VideoEditEloquentModel::factory()->processing()->create();
    $completed = VideoEditEloquentModel::factory()->completed()->create();

    $this->repository->updateProgress($processing->uuid, 150, ProcessingStage::Render);
    $this->repository->updateProgress($completed->uuid, 10, ProcessingStage::Merge);

    expect($processing->fresh()->progress_percent)->toBe(100)
        ->and($processing->fresh()->current_stage)->toBe(ProcessingStage::Render)
        ->and($completed->fresh()->progress_percent)->toBe(100)
        ->and($completed->fresh()->current_stage)->toBe(ProcessingStage::Publish);
});

it('hides edits owned by someone else', function (): void {
    $edit = VideoEditEloquentModel::factory()->create();
    $stranger = User::factory()->create();

    expect($this->repository->findOwnedByUuid($edit->uuid, $stranger->id))->toBeNull()
        ->and($this->repository->findOwnedByUuid($edit->uuid, $edit->user_id)?->is($edit))->toBeTrue();
});

it('builds the cut planner from the module config', function (): void {
    config()->set('video-edit.cuts.min_output_ms', 5_000);

    (void) app(CutPlanner::class)->plan([], 4_000);
})->throws(InvalidCutRangesException::class);
