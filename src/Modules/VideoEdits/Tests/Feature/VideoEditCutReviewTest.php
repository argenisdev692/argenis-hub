<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Domain\Ports\AiEditAnalysisPort;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\AiCutProposal;
use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Modules\VideoEdits\Infrastructure\Queue\ProcessVideoEditJob;
use Modules\VideoEdits\Tests\Support\FakeStorage;
use Modules\VideoEdits\Tests\Support\FakeVideoEditor;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Activity;

/*
| Human review of AI-proposed cuts (OWASP LLM06): the AI proposes, the owner
| decides, and only then does anything get cut.
*/

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->storage = new FakeStorage;
    $this->editor = new FakeVideoEditor;
    app()->instance(StoragePort::class, $this->storage);
    app()->instance(VideoEditorPort::class, $this->editor);

    $this->workspaceRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'video-edit-review-'.bin2hex(random_bytes(4));
    config()->set('filesystems.disks.video-edit-workspace.root', $this->workspaceRoot);

    // "Hoy vamos Outluk PAUSA vamos a Outlook" — a retake, a marker and a clean take.
    $this->transcriber = new class(new Transcript([new TranscriptWord('Hoy', 0, 1_000), new TranscriptWord('vamos', 1_000, 3_000), new TranscriptWord('Outluk', 3_000, 5_000), new TranscriptWord('PAUSA', 5_000, 7_000), new TranscriptWord('vamos', 7_000, 9_000), new TranscriptWord('a', 9_000, 10_000), new TranscriptWord('Outlook', 10_000, 12_000)], language: 'es')) implements TranscriptionPort
    {
        public int $calls = 0;

        public function __construct(private readonly Transcript $transcript) {}

        public function transcribe(string $audioPath, ?string $language = null): Transcript
        {
            $this->calls++;

            return $this->transcript;
        }
    };
    app()->instance(TranscriptionPort::class, $this->transcriber);

    $this->analyzer = new class(new AiAnalysis(cutProposals: [new AiCutProposal(CutReason::Retake, 1, 2, 0.9, 'Failed first take.'), new AiCutProposal(CutReason::PauseMarker, 3, 3, 0.99, 'Spoken pause marker.')])) implements AiEditAnalysisPort
    {
        public int $calls = 0;

        public function __construct(private readonly AiAnalysis $analysis) {}

        public function analyze(Transcript $transcript, ?ScriptDocument $script, ?string $instructions, ?int $targetDurationMinutes): AiAnalysis
        {
            $this->calls++;

            return $this->analysis;
        }
    };
    app()->instance(AiEditAnalysisPort::class, $this->analyzer);
});

afterEach(function (): void {
    File::deleteDirectory($this->workspaceRoot);
});

/**
 * An AI edit exactly as the create endpoint stores it: speech cleanup on, and a
 * source whose fingerprint is not known until the worker downloads it.
 */
function queuedAiEdit(FakeStorage $storage, FakeVideoEditor $editor, User $owner, int $sourceBytes = 100): VideoEditEloquentModel
{
    $edit = VideoEditEloquentModel::factory()->for($owner)->queued()->create([
        'mode' => VideoEditMode::AiEdit,
        'ai_consent_at' => now(),
        'parameters' => [
            'silence_removal' => ['enabled' => false, 'threshold_seconds' => null],
            'speech_cleanup' => ['enabled' => true, 'categories' => ['filler'], 'language' => null],
            'manual_ranges' => [],
            'ai_edit' => ['enabled' => true, 'consented' => true],
        ],
    ]);

    $source = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create([
        'declared_size_bytes' => $sourceBytes,
        'size_bytes' => $sourceBytes,
        'sha256' => null,
    ]);
    $storage->seed((string) $source->storage_path, $sourceBytes);
    $editor->probes = ['source-1.mp4' => new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0)];

    return $edit;
}

it('parks an AI edit for review before anything is cut or rendered', function (): void {
    $edit = queuedAiEdit($this->storage, $this->editor, VideoEditTestUsers::editor());

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $fresh = $edit->fresh(['sources', 'appliedCuts']);

    expect($fresh->status)->toBe(VideoEditStatus::AwaitingReview)
        ->and($fresh->review_expires_at?->isFuture())->toBeTrue()
        ->and($fresh->ai_review['cuts'])->toHaveCount(2)
        ->and($fresh->ai_review['reviewed_at'])->toBeNull()
        ->and($this->analyzer->calls)->toBe(1)
        ->and($this->editor->called('render'))->toBeFalse()
        ->and($fresh->result_path)->toBeNull()
        ->and($fresh->appliedCuts)->toHaveCount(0)
        // The second pass starts again from the stored sources.
        ->and($fresh->sources->every(fn ($source) => $source->storage_path !== null))->toBeTrue()
        ->and(is_dir($this->workspaceRoot.DIRECTORY_SEPARATOR.$edit->uuid))->toBeFalse();
});

it('renders only the cuts the owner approved, without asking the AI or Whisper again', function (): void {
    $owner = VideoEditTestUsers::editor();
    $edit = queuedAiEdit($this->storage, $this->editor, $owner);
    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $cuts = $edit->fresh()->ai_review['cuts'];
    $pauseMarker = collect($cuts)->firstWhere('reason', 'pause_marker');

    // The sync queue runs the render pass inside this request.
    $this->actingAs($owner)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => [$pauseMarker['id']]])
        ->assertAccepted();

    $fresh = $edit->fresh(['appliedCuts', 'cutDecisions']);
    $aiDecisions = $fresh->cutDecisions->where('producer', 'ai_analyzer');

    expect($fresh->status)->toBe(VideoEditStatus::Completed)
        ->and($aiDecisions)->toHaveCount(1)
        ->and($aiDecisions->first()->reason)->toBe(CutReason::PauseMarker)
        // The end is pulled back by the onset guard, so the next word keeps its
        // attack; the owner still approved this cut, not a wider one.
        ->and($fresh->appliedCuts->map(fn ($cut) => [$cut->start_ms, $cut->end_ms])->all())->toBe([[5_000, 6_960]])
        ->and($this->analyzer->calls)->toBe(1)
        ->and($this->transcriber->calls)->toBe(1);
});

it('renders without AI cuts when the owner keeps everything', function (): void {
    $owner = VideoEditTestUsers::editor();
    $edit = queuedAiEdit($this->storage, $this->editor, $owner);
    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $this->actingAs($owner)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => []])
        ->assertAccepted();

    $fresh = $edit->fresh(['appliedCuts', 'cutDecisions']);

    expect($fresh->status)->toBe(VideoEditStatus::Completed)
        ->and($fresh->cutDecisions->where('producer', 'ai_analyzer'))->toHaveCount(0)
        ->and($fresh->final_duration_ms)->toBe(60_000);
});

/**
 * An auto edit exactly as the create endpoint stores it, with the pause-marker
 * category on. This is the mode that had no way to remove a failed take at all:
 * the AI producer only ever runs for an AI edit, so before this existed the
 * marker was cut and the abandoned words stayed in.
 */
function queuedAutoEdit(FakeStorage $storage, FakeVideoEditor $editor, User $owner, int $sourceBytes = 100): VideoEditEloquentModel
{
    $edit = VideoEditEloquentModel::factory()->for($owner)->queued()->create([
        'mode' => VideoEditMode::AutoEdit,
        'parameters' => [
            'silence_removal' => ['enabled' => false, 'threshold_seconds' => null],
            'speech_cleanup' => ['enabled' => true, 'categories' => ['pause_marker'], 'language' => null],
            'manual_ranges' => [],
        ],
    ]);

    $source = VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create([
        'declared_size_bytes' => $sourceBytes,
        'size_bytes' => $sourceBytes,
        'sha256' => null,
    ]);
    $storage->seed((string) $source->storage_path, $sourceBytes);
    $editor->probes = ['source-1.mp4' => new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0)];

    return $edit;
}

it('parks an auto edit for review on the failed take alone', function (): void {
    $edit = queuedAutoEdit($this->storage, $this->editor, VideoEditTestUsers::editor());

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $fresh = $edit->fresh(['sources', 'appliedCuts']);
    $cuts = collect($fresh->ai_review['cuts']);
    $retake = $cuts->sole();

    expect($fresh->status)->toBe(VideoEditStatus::AwaitingReview)
        ->and($fresh->review_expires_at?->isFuture())->toBeTrue()
        // Only the take is on the review. The marker is a literal match that
        // cannot be wrong, so it is already a cut and needs no approval — it is
        // the context the owner reads the take against.
        ->and($cuts)->toHaveCount(1)
        // "Hoy vamos Outluk" — the attempt before the marker. Nothing was sent
        // anywhere to work that out.
        ->and($retake['reason'])->toBe('retake')
        ->and($retake['text'])->toBe('Hoy vamos Outluk')
        ->and($retake['start_ms'])->toBe(0)
        ->and($retake['end_ms'])->toBe(5_000)
        ->and($retake['context_after'])->toContain('PAUSA')
        // No punctuation and no pause bounded it, so the start is a guess and
        // must not start ticked.
        ->and($retake['preselected'])->toBeFalse()
        ->and($this->editor->called('render'))->toBeFalse()
        ->and($fresh->result_path)->toBeNull()
        ->and($fresh->appliedCuts)->toHaveCount(0);
});

it('renders the failed take only once the owner approves it', function (): void {
    $owner = VideoEditTestUsers::editor();
    $edit = queuedAutoEdit($this->storage, $this->editor, $owner);

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $retake = collect($edit->fresh()->ai_review['cuts'])->firstWhere('reason', 'retake');

    $this->actingAs($owner)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => [$retake['id']]])
        ->assertAccepted();

    $fresh = $edit->fresh(['appliedCuts', 'cutDecisions']);

    expect($fresh->status)->toBe(VideoEditStatus::Completed)
        ->and($fresh->cutDecisions->where('reason', 'retake'))->toHaveCount(1)
        // The marker is still cut on its own evidence; approving the take adds
        // to it rather than replacing it.
        ->and($fresh->cutDecisions->where('reason', 'pause_marker'))->toHaveCount(1)
        ->and($this->transcriber->calls)->toBe(1);
});

it('leaves an auto edit with no marker alone', function (): void {
    app()->instance(TranscriptionPort::class, new class(new Transcript([new TranscriptWord('Hoy', 0, 1_000), new TranscriptWord('vamos', 1_000, 3_000), new TranscriptWord('a', 3_000, 4_000), new TranscriptWord('Outlook.', 4_000, 6_000)], language: 'es')) implements TranscriptionPort
    {
        public int $calls = 0;

        public function __construct(private readonly Transcript $transcript) {}

        public function transcribe(string $audioPath, ?string $language = null): Transcript
        {
            $this->calls++;

            return $this->transcript;
        }
    });

    $edit = queuedAutoEdit($this->storage, $this->editor, VideoEditTestUsers::editor());

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    expect($edit->fresh()->status)->toBe(VideoEditStatus::Completed)
        // No marker, so no failed take, so nothing to review and no stop.
        ->and($edit->fresh()->review_expires_at)->toBeNull();
});

it('shows the pending failed take on the edit', function (): void {
    $owner = VideoEditTestUsers::editor();
    $edit = queuedAutoEdit($this->storage, $this->editor, $owner);

    ProcessVideoEditJob::dispatchSync($edit->uuid);

    $this->actingAs($owner)
        ->getJson("/data/admin/video-edits/{$edit->uuid}")
        ->assertOk()
        ->assertJsonPath('can_review', true)
        ->assertJsonPath('review.is_resolved', false)
        ->assertJsonCount(1, 'review.cuts')
        ->assertJsonPath('review.cuts.0.text', 'Hoy vamos Outluk')
        ->assertJsonPath('review.cuts.0.reason', 'retake');
});

it('never reuses another recording\'s transcript on a first run', function (): void {
    $owner = VideoEditTestUsers::editor();

    ProcessVideoEditJob::dispatchSync(queuedAiEdit($this->storage, $this->editor, $owner, sourceBytes: 100)->uuid);
    VideoEditEloquentModel::query()->update(['status' => VideoEditStatus::Completed->value]);
    ProcessVideoEditJob::dispatchSync(queuedAiEdit($this->storage, $this->editor, $owner, sourceBytes: 200)->uuid);

    // Different bytes, different recording: each one is transcribed.
    expect($this->transcriber->calls)->toBe(2);
});

it('shows the pending review on the edit', function (): void {
    $owner = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($owner)->awaitingReview()->create();

    $this->actingAs($owner)
        ->getJson("/data/admin/video-edits/{$edit->uuid}")
        ->assertOk()
        ->assertJsonPath('status', 'awaiting_review')
        ->assertJsonPath('can_review', true)
        ->assertJsonPath('can_delete', true)
        ->assertJsonPath('review.is_resolved', false)
        ->assertJsonCount(2, 'review.cuts')
        ->assertJsonPath('review.cuts.0.reason', 'pause_marker')
        ->assertJsonPath('review.cuts.0.text', 'PAUSA')
        ->assertJsonPath('review.cuts.0.duration_ms', 600)
        ->assertJsonPath('review.cuts.0.preselected', true)
        ->assertJsonPath('review.cuts.1.preselected', false)
        ->assertJsonStructure(['review_expires_at']);
});

it('queues the render pass with the approved cuts and audits counts only', function (): void {
    Queue::fake();
    $owner = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($owner)->awaitingReview()->create();
    $approvedId = $edit->ai_review['cuts'][0]['id'];

    $this->actingAs($owner)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => [$approvedId]])
        ->assertAccepted()
        ->assertJsonPath('status', 'queued')
        ->assertJsonPath('can_review', false)
        ->assertJsonPath('review.is_resolved', true)
        ->assertJsonPath('review.approved_cut_ids', [$approvedId]);

    $fresh = $edit->fresh();
    $review = AiCutReview::fromArray($fresh->ai_review);

    expect($fresh->status)->toBe(VideoEditStatus::Queued)
        ->and($fresh->review_expires_at)->toBeNull()
        ->and($review->isResolved())->toBeTrue()
        ->and($review->resolvedByExpiry)->toBeFalse()
        ->and(array_map(fn ($cut) => $cut->id, $review->approvedCuts()))->toBe([$approvedId]);

    Queue::assertPushedOn('video-edits', ProcessVideoEditJob::class, fn (ProcessVideoEditJob $job): bool => $job->videoEditUuid === $edit->uuid);
    expect(Activity::query()->where('description', 'video_edit.cuts_reviewed')->sole()->properties->all())
        ->toBe(['edit_uuid' => $edit->uuid, 'proposed' => 2, 'approved' => 1]);
});

it('names the stage the render pass starts at, instead of leaving it blank', function (): void {
    // The second pass re-downloads and re-merges from scratch, so progress really
    // does restart at 0. What it must not do is ALSO drop the stage: a null one
    // renders as "Waiting for a worker", which is how an accepted job ends up
    // looking like nothing is happening for the whole queue wait.
    Queue::fake();
    $owner = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($owner)->awaitingReview()->create([
        'progress_percent' => 45,
        'current_stage' => ProcessingStage::AiAnalysis,
    ]);

    $this->actingAs($owner)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => []])
        ->assertAccepted();

    $fresh = $edit->fresh();

    expect($fresh->status)->toBe(VideoEditStatus::Queued)
        ->and($fresh->progress_percent)->toBe(0)
        ->and($fresh->current_stage)->toBe(ProcessingStage::Download);
});

it('rejects a cut that this edit never proposed', function (): void {
    Queue::fake();
    $owner = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($owner)->awaitingReview()->create();
    $otherEdit = VideoEditEloquentModel::factory()->for($owner)->awaitingReview()->make();

    $this->actingAs($owner)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => [$otherEdit->ai_review['cuts'][0]['id']]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_cut_review')
        ->assertJsonValidationErrors('approved_cut_ids');

    expect($edit->fresh()->status)->toBe(VideoEditStatus::AwaitingReview);
    Queue::assertNotPushed(ProcessVideoEditJob::class);
});

it('validates the review payload', function (array $payload, string $field): void {
    Queue::fake();
    $owner = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($owner)->awaitingReview()->create();

    $this->actingAs($owner)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    Queue::assertNotPushed(ProcessVideoEditJob::class);
})->with([
    'missing answer is not "keep everything"' => [[], 'approved_cut_ids'],
    'not a list' => [['approved_cut_ids' => 'all'], 'approved_cut_ids'],
    'not a uuid' => [['approved_cut_ids' => ['1']], 'approved_cut_ids.0'],
    'duplicates' => [['approved_cut_ids' => ['0199a8a0-0000-7000-8000-000000000001', '0199a8a0-0000-7000-8000-000000000001']], 'approved_cut_ids.0'],
]);

it('refuses a review for an edit that is not waiting for one', function (): void {
    Queue::fake();
    $owner = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($owner)->processing()->create();

    $this->actingAs($owner)
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => []])
        ->assertConflict()
        ->assertJsonPath('code', 'invalid_state');

    Queue::assertNotPushed(ProcessVideoEditJob::class);
});

it('accepts only the first answer to a review', function (): void {
    Queue::fake();
    $owner = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($owner)->awaitingReview()->create();

    $this->actingAs($owner)->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => []])->assertAccepted();
    $this->actingAs($owner)->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => []])->assertConflict();

    Queue::assertPushed(ProcessVideoEditJob::class, 1);
});

it('hides another owner\'s review (OWASP §11)', function (): void {
    Queue::fake();
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->awaitingReview()->create();

    $this->actingAs(VideoEditTestUsers::editor())
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => []])
        ->assertNotFound();

    expect($edit->fresh()->status)->toBe(VideoEditStatus::AwaitingReview);
});

it('requires authentication and the create permission to review', function (): void {
    $edit = VideoEditEloquentModel::factory()->awaitingReview()->create();

    $this->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => []])->assertUnauthorized();

    $this->actingAs(VideoEditTestUsers::withoutVideoEditPermissions())
        ->postJson("/data/admin/video-edits/{$edit->uuid}/review", ['approved_cut_ids' => []])
        ->assertForbidden();
});

it('resolves an unanswered review as "keep everything" once it expires', function (): void {
    Queue::fake();
    $expired = VideoEditEloquentModel::factory()->awaitingReview()->create(['review_expires_at' => now()->subMinute()]);
    $open = VideoEditEloquentModel::factory()->awaitingReview()->create();

    $this->artisan('video-edits:sweep')->assertSuccessful();

    $review = AiCutReview::fromArray($expired->fresh()->ai_review);

    expect($expired->fresh()->status)->toBe(VideoEditStatus::Queued)
        ->and($review->isResolved())->toBeTrue()
        ->and($review->resolvedByExpiry)->toBeTrue()
        ->and($review->approvedCuts())->toBe([])
        ->and($open->fresh()->status)->toBe(VideoEditStatus::AwaitingReview);

    Queue::assertPushed(ProcessVideoEditJob::class, fn (ProcessVideoEditJob $job): bool => $job->videoEditUuid === $expired->uuid);
    Queue::assertNotPushed(ProcessVideoEditJob::class, fn (ProcessVideoEditJob $job): bool => $job->videoEditUuid === $open->uuid);
});

it('counts a parked review as the owner\'s one active edit', function (): void {
    $owner = VideoEditTestUsers::editor();
    VideoEditEloquentModel::factory()->for($owner)->awaitingReview()->create();

    expect(fn () => VideoEditEloquentModel::factory()->for($owner)->queued()->create())
        ->toThrow(UniqueConstraintViolationException::class);
});
