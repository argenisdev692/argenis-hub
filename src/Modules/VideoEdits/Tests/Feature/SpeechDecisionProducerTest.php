<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Application\Pipeline\DecisionProducerRegistry;
use Modules\VideoEdits\Application\Pipeline\Producers\SpeechDecisionProducer;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditTranscriptEloquentModel;
use Modules\VideoEdits\Tests\Support\FakeVideoEditor;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;

uses(RefreshDatabase::class);

/**
 * Counts calls so "was the provider hit again?" is a direct assertion (US-11).
 */
final class RecordingTranscriber implements TranscriptionPort
{
    public int $calls = 0;

    public function __construct(private readonly Transcript $transcript) {}

    public function transcribe(string $audioPath, ?string $language = null): Transcript
    {
        $this->calls++;

        return $this->transcript;
    }
}

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->editor = new FakeVideoEditor;
    app()->instance(VideoEditorPort::class, $this->editor);

    $this->transcriber = new RecordingTranscriber(new Transcript(
        words: [
            new TranscriptWord('Hoy', 0, 300),
            new TranscriptWord('eh', 300, 600),
            new TranscriptWord('vamos', 600, 1_000),
            new TranscriptWord('[laughs]', 1_000, 1_500),
        ],
        language: 'es',
    ));
    app()->instance(TranscriptionPort::class, $this->transcriber);
});

/**
 * @param  array<string, mixed>  $speech
 */
function speechContext(VideoEditEloquentModel $edit, array $speech, string $fingerprint = 'abc'): DecisionContext
{
    return new DecisionContext(
        mode: VideoEditMode::AutoEdit,
        parameters: ['speech_cleanup' => $speech],
        workingPath: '/workspace/edit/merged.mp4',
        workingProbe: new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        videoEditId: $edit->id,
        videoEditUuid: $edit->uuid,
        ownerId: $edit->user_id,
        sourceFingerprints: [$fingerprint],
    );
}

it('joins the pipeline through the producer tag alone', function (): void {
    $context = speechContext(
        VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create(),
        ['enabled' => true],
    );

    $names = array_map(
        static fn ($producer): string => $producer->name(),
        app(DecisionProducerRegistry::class)->forContext($context),
    );

    expect($names)->toContain(SpeechDecisionProducer::NAME);
});

it('turns transcript disfluencies into ordinary cut decisions', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();

    $decisions = app(SpeechDecisionProducer::class)->produce(
        speechContext($edit, ['enabled' => true]),
    );

    $reasons = array_map(static fn (CutDecision $d): string => $d->reason->value, $decisions);

    expect($reasons)->toContain(CutReason::Filler->value, CutReason::VocalSound->value)
        // Same contract as a V1 manual range — that is what EX-1/EX-3 promised.
        ->and($decisions[0]->origin)->toBe(DecisionOrigin::Transcription)
        ->and($decisions[0]->producer)->toBe(SpeechDecisionProducer::NAME)
        ->and($decisions[0]->startMs)->toBe(300)
        ->and($decisions[0]->evidence['category'])->toBe('filler');
});

it('stays out of the way when speech cleanup was not requested', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    $producer = app(SpeechDecisionProducer::class);

    expect($producer->supports(speechContext($edit, ['enabled' => false])))->toBeFalse()
        ->and($this->transcriber->calls)->toBe(0);
});

it('never transcribes a video with no audio track', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();

    $silent = new DecisionContext(
        mode: VideoEditMode::AutoEdit,
        parameters: ['speech_cleanup' => ['enabled' => true]],
        workingPath: '/workspace/edit/merged.mp4',
        workingProbe: new MediaProbe(60_000, 'mov,mp4', hasVideo: true, hasAudio: false),
        videoEditId: $edit->id,
        videoEditUuid: $edit->uuid,
        ownerId: $edit->user_id,
    );

    expect(app(SpeechDecisionProducer::class)->supports($silent))->toBeFalse();
});

it('applies only the categories that were requested', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();

    $decisions = app(SpeechDecisionProducer::class)->produce(
        speechContext($edit, ['enabled' => true, 'categories' => ['vocal_sound']]),
    );

    $reasons = array_unique(array_map(static fn (CutDecision $d): string => $d->reason->value, $decisions));

    expect(array_values($reasons))->toBe([CutReason::VocalSound->value]);
});

it('stores the transcript so the edit can be reported on later', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();

    app(SpeechDecisionProducer::class)->produce(speechContext($edit, ['enabled' => true]));

    $stored = VideoEditTranscriptEloquentModel::query()->where('video_edit_id', $edit->id)->first();

    expect($stored)->not->toBeNull()
        ->and($stored->word_count)->toBe(4)
        ->and($stored->language)->toBe('es')
        ->and($stored->model)->toBe(config('video-edit.speech.openai.model'));
});

it('reuses the stored transcript for identical sources instead of paying twice', function (): void {
    $user = VideoEditTestUsers::editor();
    $first = VideoEditEloquentModel::factory()->for($user)->create();
    $second = VideoEditEloquentModel::factory()->for($user)->create();
    $producer = app(SpeechDecisionProducer::class);

    $producer->produce(speechContext($first, ['enabled' => true], 'same-fingerprint'));
    $producer->produce(speechContext($second, ['enabled' => true], 'same-fingerprint'));

    expect($this->transcriber->calls)->toBe(1);
});

it('transcribes again when the sources differ', function (): void {
    $user = VideoEditTestUsers::editor();
    $producer = app(SpeechDecisionProducer::class);

    $producer->produce(speechContext(
        VideoEditEloquentModel::factory()->for($user)->create(), ['enabled' => true], 'fingerprint-a',
    ));
    $producer->produce(speechContext(
        VideoEditEloquentModel::factory()->for($user)->create(), ['enabled' => true], 'fingerprint-b',
    ));

    expect($this->transcriber->calls)->toBe(2);
});

it('never reuses another user\'s transcript', function (): void {
    $producer = app(SpeechDecisionProducer::class);

    // Same content fingerprint, different owner: a transcript is the user's
    // speech verbatim and must not cross that boundary.
    $producer->produce(speechContext(
        VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create(),
        ['enabled' => true],
        'shared-fingerprint',
    ));
    $producer->produce(speechContext(
        VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create(),
        ['enabled' => true],
        'shared-fingerprint',
    ));

    expect($this->transcriber->calls)->toBe(2);
});

it('reports its own stages so a long transcription does not look hung', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    $stages = [];

    $context = new DecisionContext(
        mode: VideoEditMode::AutoEdit,
        parameters: ['speech_cleanup' => ['enabled' => true]],
        workingPath: '/workspace/edit/merged.mp4',
        workingProbe: new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        videoEditId: $edit->id,
        videoEditUuid: $edit->uuid,
        ownerId: $edit->user_id,
        sourceFingerprints: ['abc'],
        onStageStart: function (ProcessingStage $stage) use (&$stages): void {
            $stages[] = $stage->value;
        },
    );

    app(SpeechDecisionProducer::class)->produce($context);

    expect($stages)->toBe(['audio_extraction', 'transcription', 'speech_detection']);
});

it('skips extraction and transcription stages when reusing a transcript', function (): void {
    $user = VideoEditTestUsers::editor();
    $producer = app(SpeechDecisionProducer::class);
    $producer->produce(speechContext(
        VideoEditEloquentModel::factory()->for($user)->create(), ['enabled' => true], 'shared',
    ));

    $edit = VideoEditEloquentModel::factory()->for($user)->create();
    $stages = [];
    $context = new DecisionContext(
        mode: VideoEditMode::AutoEdit,
        parameters: ['speech_cleanup' => ['enabled' => true]],
        workingPath: '/workspace/edit/merged.mp4',
        workingProbe: new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        videoEditId: $edit->id,
        videoEditUuid: $edit->uuid,
        ownerId: $edit->user_id,
        sourceFingerprints: ['shared'],
        onStageStart: function (ProcessingStage $stage) use (&$stages): void {
            $stages[] = $stage->value;
        },
    );

    $producer->produce($context);

    expect($stages)->toBe(['speech_detection']);
});

it('deletes the transcript with its edit', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    app(SpeechDecisionProducer::class)->produce(speechContext($edit, ['enabled' => true]));

    $edit->delete();

    expect(VideoEditTranscriptEloquentModel::query()->count())->toBe(0);
});
