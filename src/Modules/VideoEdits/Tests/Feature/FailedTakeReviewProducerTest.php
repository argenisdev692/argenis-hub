<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Application\Pipeline\DecisionProducerRegistry;
use Modules\VideoEdits\Application\Pipeline\Producers\FailedTakeReviewProducer;
use Modules\VideoEdits\Application\Pipeline\Producers\SpeechDecisionProducer;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Exceptions\CutReviewRequiredException;
use Modules\VideoEdits\Domain\Ports\AiCutReviewStorePort;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Tests\Support\FakeVideoEditor;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;

/**
 * The recording from OBSERVACIONES round 2: a finished sentence, then a take the
 * speaker abandoned, the spoken marker, and the same thought said again. Cutting
 * only the marker — what the module did before this producer existed — left the
 * abandoned words in place, which is the "oración que deja hueco" the
 * supervisor reported.
 */
function failedTakeTranscript(): Transcript
{
    return new Transcript(
        words: [
            new TranscriptWord('Dentro', 0, 500),
            new TranscriptWord('del', 510, 700),
            new TranscriptWord('propio', 710, 1_100),
            new TranscriptWord('hilo.', 1_110, 1_600),
            new TranscriptWord('Observa', 2_400, 2_900),
            new TranscriptWord('que', 2_910, 3_100),
            new TranscriptWord('el', 3_110, 3_200),
            new TranscriptWord('resultado', 3_210, 4_000),
            new TranscriptWord('pausa', 5_000, 5_400),
            new TranscriptWord('acá', 5_410, 5_700),
            new TranscriptWord('Es', 5_800, 5_950),
            new TranscriptWord('un', 5_960, 6_100),
            new TranscriptWord('molde.', 6_110, 6_700),
        ],
        language: 'es',
    );
}

/**
 * Counts calls, so "did this producer transcribe again?" is a direct assertion
 * rather than a guess from the cost.
 */
final class FailedTakeTranscriber implements TranscriptionPort
{
    public int $calls = 0;

    public function __construct(private readonly Transcript $transcript) {}

    public function transcribe(string $audioPath, ?string $language = null): Transcript
    {
        $this->calls++;

        return $this->transcript;
    }
}

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    app()->instance(VideoEditorPort::class, new FakeVideoEditor);
    app()->instance(TranscriptionPort::class, $this->transcriber = new FailedTakeTranscriber(failedTakeTranscript()));
});

/**
 * @param  array<string, mixed>  $speech
 */
function failedTakeContext(
    VideoEditEloquentModel $edit,
    array $speech = ['enabled' => true],
    VideoEditMode $mode = VideoEditMode::AutoEdit,
    string $fingerprint = 'take-fingerprint',
): DecisionContext {
    return new DecisionContext(
        mode: $mode,
        parameters: ['speech_cleanup' => $speech],
        workingPath: '/workspace/edit/merged.mp4',
        workingProbe: new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        videoEditId: $edit->id,
        videoEditUuid: $edit->uuid,
        ownerId: $edit->user_id,
        sourceFingerprints: [$fingerprint],
    );
}

/**
 * Runs the speech producer first — it is the one that stores the transcript this
 * producer reads, and the two are ordered by the container tag.
 */
function transcribeFor(VideoEditEloquentModel $edit, string $fingerprint = 'take-fingerprint'): void
{
    app(SpeechDecisionProducer::class)->produce(failedTakeContext($edit, fingerprint: $fingerprint));
}

function storeReview(int $videoEditId, AiCutReview $review): void
{
    app(AiCutReviewStorePort::class)->store($videoEditId, $review);
}

function reviewFor(int $videoEditId): ?AiCutReview
{
    return app(AiCutReviewStorePort::class)->forEdit($videoEditId);
}

it('joins the pipeline straight after the producer that stores the transcript', function (): void {
    $names = array_map(
        static fn ($producer): string => $producer->name(),
        app(DecisionProducerRegistry::class)->forContext(
            failedTakeContext(VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create()),
        ),
    );

    expect(array_search(FailedTakeReviewProducer::NAME, $names, true))
        ->toBe(array_search(SpeechDecisionProducer::NAME, $names, true) + 1);
});

it('parks the edit for review with the abandoned take, not just the marker', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    transcribeFor($edit);

    expect(fn () => app(FailedTakeReviewProducer::class)->produce(failedTakeContext($edit)))
        ->toThrow(CutReviewRequiredException::class);

    $review = reviewFor($edit->id);

    expect($review)->not->toBeNull()
        ->and($review->isResolved())->toBeFalse()
        ->and($review->cuts)->toHaveCount(1);

    $cut = $review->cuts[0];

    expect($cut->reason)->toBe(CutReason::Retake)
        // "Observa que el resultado" — the words before "pausa", stopping at the
        // full stop on "hilo." so the take cannot reach into the finished idea.
        ->and($cut->startMs)->toBe(2_400)
        ->and($cut->endMs)->toBe(4_000)
        ->and($cut->text)->toBe('Observa que el resultado')
        // The marker was already cut deterministically, so it is what follows.
        ->and($cut->contextAfter)->toStartWith('pausa acá')
        ->and($cut->contextBefore)->toBe('Dentro del propio hilo.')
        // Closed on sentence punctuation: strong enough to start ticked.
        ->and($cut->preselected)->toBeTrue();
});

it('applies exactly the approved takes on the pass after the review', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    transcribeFor($edit);

    try {
        app(FailedTakeReviewProducer::class)->produce(failedTakeContext($edit));
    } catch (CutReviewRequiredException) {
        // The first pass always stops here.
    }

    $pending = reviewFor($edit->id);
    storeReview($edit->id, $pending->resolve([$pending->cuts[0]->id], now()->toImmutable()));

    $decisions = app(FailedTakeReviewProducer::class)->produce(failedTakeContext($edit));

    expect($decisions)->toHaveCount(1)
        ->and($decisions[0]->reason)->toBe(CutReason::Retake)
        ->and($decisions[0]->startMs)->toBe(2_400)
        ->and($decisions[0]->endMs)->toBe(4_000)
        // Never left the machine, so it is not an AI origin.
        ->and($decisions[0]->origin)->toBe(DecisionOrigin::Transcription)
        ->and($decisions[0]->producer)->toBe(FailedTakeReviewProducer::NAME)
        ->and($decisions[0]->evidence['detector'])->toBe('failed_take');
});

it('cuts nothing when the owner unticks every proposal', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    transcribeFor($edit);

    try {
        app(FailedTakeReviewProducer::class)->produce(failedTakeContext($edit));
    } catch (CutReviewRequiredException) {
    }

    storeReview($edit->id, reviewFor($edit->id)->resolve([], now()->toImmutable()));

    expect(app(FailedTakeReviewProducer::class)->produce(failedTakeContext($edit)))->toBe([]);
});

it('refuses to render around a review nobody has answered', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    transcribeFor($edit);

    try {
        app(FailedTakeReviewProducer::class)->produce(failedTakeContext($edit));
    } catch (CutReviewRequiredException) {
    }

    // A duplicate or stale job must not slip past an open review.
    expect(fn () => app(FailedTakeReviewProducer::class)->produce(failedTakeContext($edit)))
        ->toThrow(CutReviewRequiredException::class);
});

it('reuses the transcript the speech producer stored instead of paying twice', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    transcribeFor($edit);

    expect($this->transcriber->calls)->toBe(1);

    try {
        app(FailedTakeReviewProducer::class)->produce(failedTakeContext($edit));
    } catch (CutReviewRequiredException) {
    }

    expect($this->transcriber->calls)->toBe(1);
});

it('stores no review when every marker had nothing wrong before it', function (): void {
    app()->instance(TranscriptionPort::class, new FailedTakeTranscriber(new Transcript(
        words: [
            new TranscriptWord('Esta', 0, 400),
            new TranscriptWord('frase', 410, 900),
            new TranscriptWord('esta', 910, 1_300),
            new TranscriptWord('bien.', 1_310, 1_700),
            new TranscriptWord('pausa', 1_800, 2_100),
            new TranscriptWord('acá', 2_110, 2_400),
        ],
    )));

    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    transcribeFor($edit);

    expect(app(FailedTakeReviewProducer::class)->produce(failedTakeContext($edit)))->toBe([])
        // No review record at all: an empty one would only show the owner a
        // dialog they can never open.
        ->and(reviewFor($edit->id))->toBeNull();
});

it('stays out of the way in the modes that must not get it', function (string $case): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    $producer = app(FailedTakeReviewProducer::class);

    $context = match ($case) {
        'disabled by config' => failedTakeContext($edit),
        'speech cleanup off' => failedTakeContext($edit, ['enabled' => false]),
        'both signals off' => failedTakeContext($edit, ['enabled' => true, 'categories' => ['filler']]),
        'ai edit asks the model' => failedTakeContext(
            $edit,
            ['enabled' => true],
            VideoEditMode::AiEdit,
        ),
    };

    if ($case === 'disabled by config') {
        config()->set('video-edit.failed_take.enabled', false);
    }

    if ($case === 'both signals off') {
        config()->set('video-edit.restart.enabled', false);
    }

    expect($producer->supports($context))->toBeFalse();
})->with([
    'disabled by config',
    'speech cleanup off',
    'both signals off',
    'ai edit asks the model',
]);

it('still runs with the marker category off, because a restart needs no marker', function (): void {
    // The whole point of the restart signal: on the recordings where Whisper never
    // transcribed the marker, the owner has nothing to tick unless a category the
    // UI offers can switch it off independently. It cannot.
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();

    expect(app(FailedTakeReviewProducer::class)->supports(
        failedTakeContext($edit, ['enabled' => true, 'categories' => ['filler']]),
    ))->toBeTrue();
});

it('runs when the categories are left empty, which means all of them', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();

    // Same rule SpeechDecisionProducer applies: an empty list is not "none",
    // so an owner who never opened the options still gets the review.
    expect(app(FailedTakeReviewProducer::class)->supports(failedTakeContext($edit, ['enabled' => true])))->toBeTrue()
        ->and(app(FailedTakeReviewProducer::class)->supports(failedTakeContext($edit, ['enabled' => true, 'categories' => []])))->toBeTrue();
});

it('never proposes anything for an edit with nothing to hang a review on', function (): void {
    $context = new DecisionContext(
        mode: VideoEditMode::AutoEdit,
        parameters: ['speech_cleanup' => ['enabled' => true]],
        workingPath: '/workspace/edit/merged.mp4',
        workingProbe: new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
    );

    expect(app(FailedTakeReviewProducer::class)->supports($context))->toBeFalse()
        ->and(app(FailedTakeReviewProducer::class)->produce($context))->toBe([]);
});

it('leaves the marker cut itself to the speech producer', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();

    $decisions = app(SpeechDecisionProducer::class)->produce(failedTakeContext(
        $edit,
        ['enabled' => true, 'categories' => ['pause_marker']],
    ));

    $reasons = array_map(static fn (CutDecision $decision): string => $decision->reason->value, $decisions);

    // The marker is a literal match that cannot be wrong, so it stays a
    // deterministic cut; only the take before it needs a person.
    expect($reasons)->toBe([CutReason::PauseMarker->value]);
});
