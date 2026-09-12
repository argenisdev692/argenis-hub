<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Application\Pipeline\DecisionProducerRegistry;
use Modules\VideoEdits\Application\Pipeline\Producers\AiDecisionProducer;
use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Exceptions\AiConsentRequiredException;
use Modules\VideoEdits\Domain\Ports\AiEditAnalysisPort;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\Ports\TranscriptStorePort;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\AiCutProposal;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\ScriptDocument;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;

uses(RefreshDatabase::class);

/**
 * Returns a fixed analysis and records what it was asked — so "was the script
 * sent?" and "was anything sent at all?" are direct assertions.
 */
final class RecordingAnalyzer implements AiEditAnalysisPort
{
    public int $calls = 0;

    public ?ScriptDocument $receivedScript = null;

    public ?string $receivedInstructions = null;

    public function __construct(private AiAnalysis $analysis) {}

    public function analyze(
        Transcript $transcript,
        ?ScriptDocument $script,
        ?string $instructions,
        ?int $targetDurationMinutes,
    ): AiAnalysis {
        $this->calls++;
        $this->receivedScript = $script;
        $this->receivedInstructions = $instructions;

        return $this->analysis;
    }
}

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    // Word 3 is "PAUSA", words 1-2 the failed take before it.
    $this->transcript = new Transcript([
        new TranscriptWord('Hoy', 0, 300),
        new TranscriptWord('vamos', 300, 900),
        new TranscriptWord('Outluk', 900, 1_500),
        new TranscriptWord('PAUSA', 1_500, 2_100),
        new TranscriptWord('Outlook', 2_100, 2_800),
    ]);

    $this->analysis = new AiAnalysis(
        cutProposals: [
            new AiCutProposal(CutReason::Retake, 1, 2, 0.92, 'mispronounced Outlook'),
            new AiCutProposal(CutReason::PauseMarker, 3, 3, 0.99, 'PAUSA'),
        ],
        recommendations: [
            new AiRecommendation(AiRecommendationKind::Reduce, 'Intro runs long', 'Could be 30 seconds.'),
        ],
        conclusion: 'Two retakes removed.',
    );

    $this->analyzer = new RecordingAnalyzer($this->analysis);
    app()->instance(AiEditAnalysisPort::class, $this->analyzer);
});

/**
 * @param  array<string, mixed>  $aiEdit
 */
function aiContext(VideoEditEloquentModel $edit, array $aiEdit = ['enabled' => true, 'consented' => true]): DecisionContext
{
    return new DecisionContext(
        mode: VideoEditMode::AiEdit,
        parameters: ['ai_edit' => $aiEdit],
        workingPath: '/workspace/edit/merged.mp4',
        workingProbe: new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        videoEditId: $edit->id,
        videoEditUuid: $edit->uuid,
        ownerId: $edit->user_id,
        sourceFingerprints: ['fp'],
    );
}

function storeTranscriptFor(VideoEditEloquentModel $edit, Transcript $transcript): void
{
    app(TranscriptStorePort::class)->store(
        $edit->id,
        (new DecisionContext(
            mode: VideoEditMode::AiEdit,
            parameters: [],
            workingPath: '/x',
            workingProbe: new MediaProbe(1, 'mp4', true, true),
            sourceFingerprints: ['fp'],
        ))->contentKey(),
        $transcript,
        'openai',
        'whisper-1',
    );
}

it('joins the pipeline through the producer tag alone', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();

    $names = array_map(
        static fn ($producer): string => $producer->name(),
        app(DecisionProducerRegistry::class)->forContext(aiContext($edit)),
    );

    expect($names)->toContain(AiDecisionProducer::NAME);
});

it('refuses to send anything without explicit consent', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    expect(fn () => app(AiDecisionProducer::class)->produce(
        aiContext($edit, ['enabled' => true, 'consented' => false]),
    ))->toThrow(AiConsentRequiredException::class);

    // The gate is before the provider, not after it (R9).
    expect($this->analyzer->calls)->toBe(0);
});

it('resolves word indices to Whisper\'s exact milliseconds', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    $decisions = app(AiDecisionProducer::class)->produce(aiContext($edit));

    // Retake spans words 1..2 → 300 ms to 1 500 ms; the model never said a time.
    expect($decisions)->toHaveCount(2)
        ->and($decisions[0]->startMs)->toBe(300)
        ->and($decisions[0]->endMs)->toBe(1_500)
        ->and($decisions[0]->reason)->toBe(CutReason::Retake)
        ->and($decisions[0]->origin)->toBe(DecisionOrigin::Ai)
        // Pause marker is word 3 alone → 1 500 ms to 2 100 ms.
        ->and($decisions[1]->startMs)->toBe(1_500)
        ->and($decisions[1]->endMs)->toBe(2_100)
        ->and($decisions[1]->reason)->toBe(CutReason::PauseMarker);
});

it('discards proposals below the confidence threshold', function (): void {
    config()->set('video-edit.ai.auto_apply_above_confidence', 0.95);
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    $decisions = app(AiDecisionProducer::class)->produce(aiContext($edit));

    // The 0.92 retake is dropped; the 0.99 pause marker survives (R6).
    expect($decisions)->toHaveCount(1)
        ->and($decisions[0]->reason)->toBe(CutReason::PauseMarker);
});

it('never turns a recommendation into a cut', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    $decisions = app(AiDecisionProducer::class)->produce(aiContext($edit));
    $reasons = array_map(static fn (CutDecision $d): string => $d->reason->value, $decisions);

    expect($reasons)->not->toContain('reduce', 'off_script')
        ->and(array_unique($reasons))->toEqualCanonicalizing(['retake', 'pause_marker']);
});

it('stores the recommendations but never the raw response', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    app(AiDecisionProducer::class)->produce(aiContext($edit));

    $stored = app(AiReportStorePort::class)->forEdit($edit->id);
    $columns = VideoEditEloquentModel::query()->whereKey($edit->id)->first()->ai_report;

    expect($stored?->recommendations)->toHaveCount(1)
        ->and($stored->recommendations[0]->kind)->toBe(AiRecommendationKind::Reduce)
        ->and($stored->conclusion)->toBe('Two retakes removed.')
        // Only the two validated keys — no raw provider payload (R10).
        ->and(array_keys($columns))->toEqualCanonicalizing(['recommendations', 'conclusion']);
});

it('does nothing when no transcript was produced', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();

    expect(app(AiDecisionProducer::class)->produce(aiContext($edit)))->toBe([])
        ->and($this->analyzer->calls)->toBe(0);
});

it('reports the AI analysis stage so the bar keeps moving', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);
    $stages = [];

    $context = new DecisionContext(
        mode: VideoEditMode::AiEdit,
        parameters: ['ai_edit' => ['enabled' => true, 'consented' => true]],
        workingPath: '/workspace/edit/merged.mp4',
        workingProbe: new MediaProbe(60_000, 'mov,mp4', true, true),
        videoEditId: $edit->id,
        videoEditUuid: $edit->uuid,
        ownerId: $edit->user_id,
        sourceFingerprints: ['fp'],
        onStageStart: function (ProcessingStage $stage) use (&$stages): void {
            $stages[] = $stage->value;
        },
    );

    app(AiDecisionProducer::class)->produce($context);

    expect($stages)->toBe(['ai_analysis']);
});

it('passes the user instructions through as content', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    app(AiDecisionProducer::class)->produce(aiContext($edit, [
        'enabled' => true,
        'consented' => true,
        'instructions' => 'Actúa como mi editor de video senior.',
    ]));

    expect($this->analyzer->receivedInstructions)->toBe('Actúa como mi editor de video senior.');
});

it('deletes the report with its edit', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);
    app(AiDecisionProducer::class)->produce(aiContext($edit));

    $edit->delete();

    expect(VideoEditEloquentModel::query()->whereKey($edit->id)->exists())->toBeFalse();
});
