<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\VideoEdits\Application\Commands\MarkVideoEditFailedHandler;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Modules\VideoEdits\Infrastructure\Queue\ProcessVideoEditJob;
use Modules\VideoEdits\Tests\Support\FakeStorage;
use Modules\VideoEdits\Tests\Support\FakeVideoEditor;
use Shared\Domain\Ports\StoragePort;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->storage = new FakeStorage;
    $this->editor = new FakeVideoEditor;
    app()->instance(StoragePort::class, $this->storage);
    app()->instance(VideoEditorPort::class, $this->editor);

    $this->workspaceRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'video-edit-pipeline-'.bin2hex(random_bytes(4));
    config()->set('filesystems.disks.video-edit-workspace.root', $this->workspaceRoot);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function queuedEdit(FakeStorage $storage, array $attributes = [], int $sourceCount = 2, bool $merge = false): VideoEditEloquentModel
{
    $factory = VideoEditEloquentModel::factory()->queued();
    $edit = ($merge ? $factory->merge() : $factory)->create($attributes);

    foreach (range(1, $sourceCount) as $position) {
        $source = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create([
            'position' => $position,
            'declared_size_bytes' => 100,
            'size_bytes' => 100,
        ]);
        $storage->seed((string) $source->storage_path, 100);
    }

    return $edit->load('sources', 'user');
}

function probeLasting(int $durationMs, string $container = 'mov,mp4,m4a,3gp,3g2,mj2', bool $hasAudio = true): MediaProbe
{
    return new MediaProbe($durationMs, $container, true, $hasAudio, 1920, 1080, 30.0, 'h264', $hasAudio ? 'aac' : null);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function cutParameters(array $overrides = []): array
{
    return array_replace([
        'silence_removal' => ['enabled' => true, 'threshold_seconds' => 1.0],
        'manual_ranges' => [],
    ], $overrides);
}

it('runs an auto edit end to end: merge, cut, render, publish and clean up', function (): void {
    $edit = queuedEdit($this->storage, ['parameters' => cutParameters([
        'manual_ranges' => [['start_ms' => 50_000, 'end_ms' => 52_000, 'note' => 'retake']],
    ])]);
    $sourcePaths = $edit->sources->pluck('storage_path')->all();
    $this->editor->probes = [
        'source-1.mp4' => probeLasting(30_000),
        'source-2.mp4' => probeLasting(30_000),
        'merged.mp4' => probeLasting(60_000),
    ];
    $this->editor->silences = [new TimeRange(10_000, 12_000)];

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $fresh = $edit->fresh(['sources', 'appliedCuts', 'cutDecisions', 'user']);
    $resultPath = "video-edits/{$fresh->user->uuid}/{$edit->uuid}/result.mp4";

    expect($fresh->status)->toBe(VideoEditStatus::Completed)
        ->and($fresh->progress_percent)->toBe(100)
        ->and($fresh->attempts)->toBe(1)
        ->and($fresh->original_duration_ms)->toBe(60_000)
        ->and($fresh->removed_duration_ms)->toBe(3_700)
        ->and($fresh->final_duration_ms)->toBe(56_300)
        ->and($fresh->applied_cut_count)->toBe(2)
        ->and($fresh->appliedCuts->map(fn ($cut) => [$cut->start_ms, $cut->end_ms])->all())->toBe([[10_150, 11_850], [50_000, 52_000]])
        ->and($fresh->cutDecisions)->toHaveCount(2)
        ->and($fresh->cutDecisions->every(fn ($decision) => $decision->outcome === DecisionOutcome::Applied))->toBeTrue()
        ->and($fresh->effective_settings['output_profile']['width'])->toBe(1920)
        ->and($fresh->result_path)->toBe($resultPath)
        ->and($this->storage->exists($resultPath))->toBeTrue()
        ->and($fresh->sources->every(fn ($source) => $source->storage_path === null && $source->sha256 !== null))->toBeTrue()
        ->and($fresh->sources_purged_at)->not->toBeNull()
        ->and($this->storage->deleted)->toContain(...$sourcePaths)
        ->and($this->editor->callsTo('merge')[0]['intermediate'])->toBeTrue()
        ->and($this->editor->callsTo('render')[0]['keepRanges'])->toHaveCount(3)
        ->and(is_dir($this->workspaceRoot.DIRECTORY_SEPARATOR.$edit->uuid))->toBeFalse();
});

it('merges clips into the final file without a render pass', function (): void {
    $edit = queuedEdit($this->storage, merge: true);
    $this->editor->probes = ['merged.mp4' => probeLasting(120_000)];

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $fresh = $edit->fresh();

    expect($fresh->status)->toBe(VideoEditStatus::Completed)
        ->and($fresh->final_duration_ms)->toBe(120_000)
        ->and($fresh->applied_cut_count)->toBe(0)
        ->and($this->editor->callsTo('merge')[0]['intermediate'])->toBeFalse()
        ->and($this->editor->called('render'))->toBeFalse()
        ->and($this->editor->called('detectSilences'))->toBeFalse()
        ->and($this->storage->objects[$fresh->result_path])->toBe('merged');
});

it('renders a single clip directly, without a merge pass', function (): void {
    $edit = queuedEdit($this->storage, ['parameters' => cutParameters()], sourceCount: 1);
    $this->editor->probes = ['source-1.mp4' => probeLasting(30_000)];

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $render = $this->editor->callsTo('render')[0];

    expect($edit->fresh()->status)->toBe(VideoEditStatus::Completed)
        ->and($this->editor->called('merge'))->toBeFalse()
        ->and(basename($render['inputPath']))->toBe('source-1.mp4')
        ->and(array_map(fn (TimeRange $range) => [$range->startMs, $range->endMs], $render['keepRanges']))->toBe([[0, 30_000]]);
});

it('clamps a user range that ends inside the few milliseconds a merge can lose', function (): void {
    $edit = queuedEdit($this->storage, ['parameters' => cutParameters([
        'silence_removal' => ['enabled' => false, 'threshold_seconds' => null],
        'manual_ranges' => [['start_ms' => 59_000, 'end_ms' => 60_000, 'note' => null]],
    ])]);
    $this->editor->probes = [
        'source-1.mp4' => probeLasting(30_000),
        'source-2.mp4' => probeLasting(30_000),
        'merged.mp4' => probeLasting(59_980),
    ];

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $fresh = $edit->fresh(['appliedCuts']);

    expect($fresh->status)->toBe(VideoEditStatus::Completed)
        ->and($fresh->appliedCuts->first()->end_ms)->toBe(59_980)
        ->and($fresh->final_duration_ms)->toBe(59_000);
});

it('fails before merging when a manual range falls outside the videos', function (): void {
    $edit = queuedEdit($this->storage, ['parameters' => cutParameters([
        'manual_ranges' => [
            ['start_ms' => 1_000, 'end_ms' => 2_000, 'note' => null],
            ['start_ms' => 70_000, 'end_ms' => 71_000, 'note' => null],
        ],
    ])]);
    $this->editor->probes = ['source-1.mp4' => probeLasting(30_000), 'source-2.mp4' => probeLasting(30_000)];

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $fresh = $edit->fresh(['sources']);

    expect($fresh->status)->toBe(VideoEditStatus::Failed)
        ->and($fresh->failure_code)->toBe('invalid_cut_ranges')
        ->and($fresh->failure_details)->toBe(['manual_ranges' => [['index' => 1, 'error' => 'end_beyond_duration']]])
        ->and($fresh->attempts)->toBe(1)
        ->and($fresh->sources_expire_at?->isFuture())->toBeTrue()
        ->and($fresh->sources->every(fn ($source) => $source->storage_path !== null))->toBeTrue()
        ->and($this->editor->called('merge'))->toBeFalse()
        ->and($this->editor->called('render'))->toBeFalse()
        ->and(is_dir($this->workspaceRoot.DIRECTORY_SEPARATOR.$edit->uuid))->toBeFalse();
});

it('rejects files that are not a supported video', function (): void {
    $edit = queuedEdit($this->storage, ['parameters' => cutParameters()]);
    $this->editor->probes = ['source-1.mp4' => probeLasting(30_000, container: 'avi')];

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $fresh = $edit->fresh();

    expect($fresh->status)->toBe(VideoEditStatus::Failed)
        ->and($fresh->failure_code)->toBe('invalid_media')
        ->and($fresh->failure_details)->toBe(['sources' => [['position' => 1, 'error' => 'unsupported_format']]]);
});

it('stores a safe message and keeps the sources when processing breaks', function (): void {
    $edit = queuedEdit($this->storage, ['parameters' => cutParameters()]);
    $this->editor->probes = ['merged.mp4' => probeLasting(120_000)];
    $this->editor->failOn = 'render';
    $this->editor->failure = new RuntimeException('ffmpeg exited 1 while writing /var/workspace/secret/result.mp4');

    expect(fn () => ProcessVideoEditJob::dispatchSync($edit->uuid))->toThrow(RuntimeException::class);

    $fresh = $edit->fresh(['sources']);

    expect($fresh->status)->toBe(VideoEditStatus::Failed)
        ->and($fresh->failure_code)->toBe('processing_error')
        ->and($fresh->failure_message)->not->toContain('/var/workspace')
        ->and($fresh->sources->every(fn ($source) => $source->storage_path !== null))->toBeTrue()
        ->and($this->storage->objects)->toHaveCount(2)
        ->and(is_dir($this->workspaceRoot.DIRECTORY_SEPARATOR.$edit->uuid))->toBeFalse();
});

it('does nothing when the edit was deleted before a worker picked it up', function (): void {
    $edit = queuedEdit($this->storage);
    $uuid = $edit->uuid;
    $edit->delete();

    ProcessVideoEditJob::dispatchSync($uuid);

    expect($this->editor->calls)->toBe([]);
});

it('ignores stale jobs for edits that already finished', function (): void {
    $edit = VideoEditEloquentModel::factory()->completed()->create();

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    expect($edit->fresh()->status)->toBe(VideoEditStatus::Completed)
        ->and($this->editor->calls)->toBe([]);
});

it('records timeouts with their own failure code', function (): void {
    $edit = VideoEditEloquentModel::factory()->processing()->create();

    app(MarkVideoEditFailedHandler::class)->handleTimeout($edit->uuid);

    $fresh = $edit->fresh();

    expect($fresh->status)->toBe(VideoEditStatus::Failed)
        ->and($fresh->failure_code)->toBe('processing_timeout')
        ->and($fresh->sources_expire_at?->isFuture())->toBeTrue();
});
