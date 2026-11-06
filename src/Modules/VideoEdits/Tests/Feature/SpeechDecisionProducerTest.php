<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Application\Pipeline\DecisionProducerRegistry;
use Modules\VideoEdits\Application\Pipeline\Producers\SpeechDecisionProducer;
use Modules\VideoEdits\Application\Transcription\TranscriptionIdentity;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\SpeechCategory;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\Services\SpeechDisfluencyDetector;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\SpeechDetection;
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

/*
|----------------------------------------------------------------------
| Reuse is scoped to the transcriber that produced the timings
|----------------------------------------------------------------------
|
| Word timings are model-specific: whisper-1 and gemini-3.5-transcribe place
| the same word milliseconds apart, and every cut in this module is addressed
| by word index and resolved against those timings. So a transcript from
| another model is not a cheaper copy of this one — it is a wrong one.
|
| This is not hypothetical for this module. whisper-1 normalises fillers away,
| so switching to gemini's `verbatim` mode is what makes a spoken "pausa"
| reachable at all; a store that keyed reuse on the source fingerprint alone
| handed the whisper transcript straight back and every marker stayed invisible
| in the delivered video, with the edit reported as successful.
*/
it('transcribes again after switching provider, rather than reusing the old model timings', function (): void {
    config()->set('video-edit.provider', 'openai');

    $user = VideoEditTestUsers::editor();
    $producer = app(SpeechDecisionProducer::class);
    $producer->produce(speechContext(
        VideoEditEloquentModel::factory()->for($user)->create(), ['enabled' => true], 'shared-fingerprint',
    ));

    expect($this->transcriber->calls)->toBe(1);

    config()->set('video-edit.provider', 'gemini');

    // A fresh producer: the identity is a singleton, so the running job keeps
    // the provider it started with. A new edit is a new job.
    app()->forgetInstance(TranscriptionIdentity::class);
    app(SpeechDecisionProducer::class)->produce(speechContext(
        VideoEditEloquentModel::factory()->for($user)->create(), ['enabled' => true], 'shared-fingerprint',
    ));

    expect($this->transcriber->calls)->toBe(2);
});

it('labels a stored transcript with the provider that actually produced it', function (): void {
    config()->set('video-edit.provider', 'gemini');
    app()->forgetInstance(TranscriptionIdentity::class);

    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    app(SpeechDecisionProducer::class)->produce(speechContext($edit, ['enabled' => true]));

    $stored = VideoEditTranscriptEloquentModel::query()->where('video_edit_id', $edit->id)->first();

    expect($stored->provider)->toBe('gemini')
        ->and($stored->model)->toBe(config('video-edit.speech.gemini.model'));
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

/*
|--------------------------------------------------------------------------
| The shipped dictionaries (P1 / P3)
|--------------------------------------------------------------------------
|
| The unit tests carry their own copy of the dictionaries, which is right for
| proving the RULES. These prove the CONFIG is wired up and behaves, resolved
| through the container exactly as a real edit resolves it — so a regex typo in
| config/video-edit.php cannot pass CI while silently disabling the feature, and
| the test copy cannot drift into proving something the shipped file does not do.
|
*/

it('removes a spoken pause marker from a real recording using the shipped dictionary', function (): void {
    $this->transcriber = new RecordingTranscriber(new Transcript(
        words: [
            new TranscriptWord('Contestamos', 0, 900),
            new TranscriptWord('pausa', 900, 1_400),
            new TranscriptWord('acá', 1_400, 1_800),
            new TranscriptWord('y', 1_800, 1_900),
            new TranscriptWord('empieza', 1_900, 2_600),
        ],
        language: 'es',
    ));
    app()->instance(TranscriptionPort::class, $this->transcriber);

    $decisions = app(SpeechDecisionProducer::class)->produce(speechContext(
        VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create(),
        ['enabled' => true, 'categories' => ['pause_marker']],
        'pause-marker',
    ));

    expect($decisions)->toHaveCount(1)
        ->and($decisions[0]->reason)->toBe(CutReason::PauseMarker)
        ->and($decisions[0]->origin)->toBe(DecisionOrigin::Transcription)
        ->and($decisions[0]->startMs)->toBe(900)
        ->and($decisions[0]->endMs)->toBe(1_800);
});

it('removes a held vowel from a real recording using the shipped patterns', function (): void {
    $this->transcriber = new RecordingTranscriber(new Transcript(
        words: [
            new TranscriptWord('y', 0, 200),
            new TranscriptWord('deee', 200, 900),
            new TranscriptWord('para', 900, 1_300),
            new TranscriptWord('queee', 1_300, 2_100),
            // Ordinary words the patterns must leave alone.
            new TranscriptWord('coffee', 2_100, 2_700),
            new TranscriptWord('ana', 2_700, 3_100),
        ],
        language: 'es',
    ));
    app()->instance(TranscriptionPort::class, $this->transcriber);

    $decisions = app(SpeechDecisionProducer::class)->produce(speechContext(
        VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create(),
        ['enabled' => true, 'categories' => ['filler']],
        'elongation',
    ));

    expect(array_map(
        static fn (CutDecision $decision): array => [$decision->reason->value, $decision->startMs],
        $decisions,
    ))->toBe([['filler', 200], ['filler', 1_300]]);
});

it('asks Whisper for the spoken markers it has to transcribe', function (): void {
    // Whisper normalises an unprompted interjection away, and a marker that is
    // never transcribed cannot be found by the dictionary or by the model. The
    // style prompt is the only lever, so its presence is asserted rather than
    // assumed.
    $spanish = (string) config('video-edit.speech.openai.prompts.es');

    expect($spanish)
        ->toContain('Pausa')
        ->toContain('Pausa aquí')
        ->toContain('Pausa acá')
        ->and((string) config('video-edit.speech.openai.prompts.en'))->toContain('Pause');
});

it('offers the marker as a category with nothing left out of the default set', function (): void {
    // An AI edit sends no categories at all, and "all of them" is the documented
    // meaning of that — so a marker has to be inside the default set or it is
    // only ever removed for a user who found the switch.
    expect(SpeechCategory::values())->toContain('pause_marker')
        ->and(count(SpeechCategory::all()))->toBe(count(SpeechCategory::values()));
});

it('removes real padding but keeps a transition like "muy bien"', function (): void {
    // "Okay, okey" are padding in every register. "Muy bien" is not: in this
    // speaker's lessons it is the hinge that moves to the next step ("muy bien,
    // ahora..."), so the dictionary no longer removes it. The same test also
    // pins "vamos" as content: it is not in any filler list, and a producer that
    // removes it is cutting a take, not a tic.
    $detectedAs = static fn (array $detections): array => array_map(
        static fn (SpeechDetection $detection): array => [$detection->category->value, $detection->startMs, $detection->endMs],
        $detections,
    );

    expect($detectedAs(app(SpeechDisfluencyDetector::class)->detect(
        new Transcript([
            new TranscriptWord('Okay', 1_000, 1_400),
            new TranscriptWord('okey', 1_600, 1_900),
            new TranscriptWord('muy', 2_200, 2_400),
            new TranscriptWord('bien', 2_450, 2_800),
            new TranscriptWord('vamos', 3_100, 3_600),
        ]),
        [SpeechCategory::FillerWord],
    )))->toBe([
        ['filler_word', 1_000, 1_400],
        ['filler_word', 1_600, 1_900],
    ]);
});

it('keeps a filler word inside a sentence that still reads', function (): void {
    // The trade every entry in this dictionary makes, asserted so it stays a
    // deliberate one rather than an accident: "okay" is a word someone can be
    // SAYING, and this dictionary cannot tell the two apart.
    $detections = app(SpeechDisfluencyDetector::class)->detect(
        new Transcript([
            new TranscriptWord('Escribir', 1_000, 1_300),
            new TranscriptWord('Okay', 1_350, 1_600),
            new TranscriptWord('en', 1_650, 1_750),
            new TranscriptWord('el', 1_800, 1_850),
            new TranscriptWord('campo', 1_900, 2_400),
        ]),
        [SpeechCategory::FillerWord],
    );

    expect($detections)->toHaveCount(1)
        ->and($detections[0]->startMs)->toBe(1_350)
        ->and($detections[0]->endMs)->toBe(1_600);
});

it('asks Whisper to keep the fillers in the transcript at all', function (): void {
    // The other half of the fix. Whisper tidies its transcript and drops
    // interjections unless the prompt shows it verbatim speech, so on a recording
    // where the word was normalised away the dictionary alone cannot help — there
    // is nothing in the transcript left to match.
    $prompt = (string) config('video-edit.speech.openai.prompts.es');

    expect($prompt)->toContain('Okay')
        ->and($prompt)->toContain('Okey')
        ->and($prompt)->toContain('Muy bien');
});
