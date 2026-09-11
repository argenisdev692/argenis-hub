<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\VideoEdits\Application\Pipeline\DecisionProducerRegistry;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Modules\VideoEdits\Infrastructure\Queue\ProcessVideoEditJob;
use Modules\VideoEdits\Tests\Support\FakeStorage;
use Modules\VideoEdits\Tests\Support\FakeVideoEditor;
use Modules\VideoEdits\Tests\Support\FixedDecisionProducer;
use Shared\Domain\Ports\StoragePort;

/*
| Roadmap readiness (spec §4.1 delivery principle, §11): a new decision producer
| — standing in for V2 speech detection or V3 AI editing — is plugged in by
| tagging it, and its decisions flow through validation, planning, rendering,
| persistence and deletion without any change to those parts.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->storage = new FakeStorage;
    $this->editor = new FakeVideoEditor;
    $this->editor->probes = ['source-1.mp4' => new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0)];
    app()->instance(StoragePort::class, $this->storage);
    app()->instance(VideoEditorPort::class, $this->editor);

    $this->workspaceRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'video-edit-roadmap-'.bin2hex(random_bytes(4));
    config()->set('filesystems.disks.video-edit-workspace.root', $this->workspaceRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

it('renders decisions from a newly plugged-in producer and reports the ones it rejects', function (): void {
    app()->instance(FixedDecisionProducer::class, new FixedDecisionProducer([
        new CutDecision(
            producer: FixedDecisionProducer::NAME,
            reason: CutReason::Manual,
            origin: DecisionOrigin::SystemDetection,
            startMs: 20_000,
            endMs: 20_600,
            confidence: 0.87,
            evidence: ['token' => 'eh', 'model' => 'fixture-v1'],
        ),
        new CutDecision(
            producer: FixedDecisionProducer::NAME,
            reason: CutReason::Manual,
            origin: DecisionOrigin::SystemDetection,
            startMs: 30_000,
            endMs: 31_000,
            confidence: 1.4, // out of range → rejected, but not fatal for a non-user producer
        ),
    ]));
    app()->tag([FixedDecisionProducer::class], DecisionProducerRegistry::TAG);

    $edit = VideoEditEloquentModel::factory()->queued()->create([
        'parameters' => ['silence_removal' => ['enabled' => false, 'threshold_seconds' => null], 'manual_ranges' => []],
    ]);
    $source = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create(['declared_size_bytes' => 100, 'size_bytes' => 100]);
    $this->storage->seed((string) $source->storage_path, 100);

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $fresh = $edit->fresh(['appliedCuts', 'cutDecisions']);
    $applied = $fresh->cutDecisions->firstWhere('outcome', DecisionOutcome::Applied);
    $rejected = $fresh->cutDecisions->firstWhere('outcome', DecisionOutcome::Rejected);

    expect($fresh->status)->toBe(VideoEditStatus::Completed)
        ->and($fresh->appliedCuts->map(fn ($cut) => [$cut->start_ms, $cut->end_ms])->all())->toBe([[20_000, 20_600]])
        ->and($fresh->appliedCuts->first()->origins)->toBe(['system_detection'])
        ->and($fresh->final_duration_ms)->toBe(59_400)
        ->and($fresh->rejected_decision_count)->toBe(1)
        ->and($applied->producer)->toBe(FixedDecisionProducer::NAME)
        ->and($applied->confidence)->toBe('0.870')
        ->and($applied->evidence)->toBe(['token' => 'eh', 'model' => 'fixture-v1'])
        ->and($rejected->rejection_reason)->toBe('confidence_out_of_range')
        ->and($this->editor->callsTo('render')[0]['keepRanges'])->toHaveCount(2);

    // Deletion needs no knowledge of the new producer either.
    $fresh->delete();

    expect(VideoEditEloquentModel::query()->count())->toBe(0);
});
