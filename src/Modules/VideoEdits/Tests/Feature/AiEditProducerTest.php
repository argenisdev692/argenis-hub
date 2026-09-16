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
use Modules\VideoEdits\Domain\Exceptions\CutReviewRequiredException;
use Modules\VideoEdits\Domain\Ports\AiCutReviewStorePort;
use Modules\VideoEdits\Domain\Ports\AiEditAnalysisPort;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\Ports\TranscriptStorePort;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\AiCutProposal;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
use Modules\VideoEdits\Domain\ValueObjects\AiReviewableCut;
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

/**
 * Runs the analysis pass, which ends by handing the proposals to the owner.
 */
function runFirstPass(VideoEditEloquentModel $edit, ?DecisionContext $context = null): void
{
    try {
        app(AiDecisionProducer::class)->produce($context ?? aiContext($edit));
    } catch (CutReviewRequiredException) {
        return;
    }

    throw new RuntimeException('Expected the analysis pass to hold the proposals for review.');
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

it('holds every proposal for review instead of cutting', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    expect(fn () => app(AiDecisionProducer::class)->produce(aiContext($edit)))
        ->toThrow(CutReviewRequiredException::class);

    $review = app(AiCutReviewStorePort::class)->forEdit($edit->id);

    expect($review)->not->toBeNull()
        ->and($review->isResolved())->toBeFalse()
        ->and($review->approvedCuts())->toBe([])
        ->and($review->cuts)->toHaveCount(2);
});

it('resolves word indices to Whisper\'s exact milliseconds', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    runFirstPass($edit);
    $cuts = app(AiCutReviewStorePort::class)->forEdit($edit->id)->cuts;

    // Retake spans words 1..2 → 300 ms to 1 500 ms; the model never said a time.
    expect($cuts[0]->startMs)->toBe(300)
        ->and($cuts[0]->endMs)->toBe(1_500)
        ->and($cuts[0]->reason)->toBe(CutReason::Retake)
        // What the owner reads is what Whisper heard, not what the model says.
        ->and($cuts[0]->text)->toBe('vamos Outluk')
        ->and($cuts[0]->contextBefore)->toBe('Hoy')
        ->and($cuts[0]->contextAfter)->toBe('PAUSA Outlook')
        ->and($cuts[0]->explanation)->toBe('mispronounced Outlook')
        // Pause marker is word 3 alone → 1 500 ms to 2 100 ms.
        ->and($cuts[1]->startMs)->toBe(1_500)
        ->and($cuts[1]->endMs)->toBe(2_100)
        ->and($cuts[1]->reason)->toBe(CutReason::PauseMarker)
        ->and($cuts[1]->text)->toBe('PAUSA');
});

it('shows low-confidence proposals unticked instead of discarding them', function (): void {
    config()->set('video-edit.ai.preselect_above_confidence', 0.95);
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    runFirstPass($edit);
    $cuts = app(AiCutReviewStorePort::class)->forEdit($edit->id)->cuts;

    // The 0.92 retake is still offered, just not preselected; the 0.99 marker is.
    expect($cuts)->toHaveCount(2)
        ->and($cuts[0]->reason)->toBe(CutReason::Retake)
        ->and($cuts[0]->preselected)->toBeFalse()
        ->and($cuts[1]->reason)->toBe(CutReason::PauseMarker)
        ->and($cuts[1]->preselected)->toBeTrue();
});

it('applies exactly the approved cuts without asking the model again', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);
    runFirstPass($edit);

    $reviews = app(AiCutReviewStorePort::class);
    $pending = $reviews->forEdit($edit->id);
    $reviews->store($edit->id, $pending->resolve([$pending->cuts[1]->id], new DateTimeImmutable));

    $decisions = app(AiDecisionProducer::class)->produce(aiContext($edit));

    expect($this->analyzer->calls)->toBe(1)
        ->and($decisions)->toHaveCount(1)
        ->and($decisions[0]->reason)->toBe(CutReason::PauseMarker)
        ->and($decisions[0]->origin)->toBe(DecisionOrigin::Ai)
        ->and($decisions[0]->startMs)->toBe(1_500)
        ->and($decisions[0]->endMs)->toBe(2_100)
        ->and($decisions[0]->evidence['review_cut_id'])->toBe($pending->cuts[1]->id);
});

it('cuts nothing when the owner keeps everything', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);
    runFirstPass($edit);

    $reviews = app(AiCutReviewStorePort::class);
    $reviews->store($edit->id, $reviews->forEdit($edit->id)->resolve([], new DateTimeImmutable));

    expect(app(AiDecisionProducer::class)->produce(aiContext($edit)))->toBe([]);
});

it('refuses to render around a review that is still open', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);
    runFirstPass($edit);

    expect(fn () => app(AiDecisionProducer::class)->produce(aiContext($edit)))
        ->toThrow(CutReviewRequiredException::class)
        ->and($this->analyzer->calls)->toBe(1);
});

it('renders straight away when the AI proposes nothing', function (): void {
    app()->instance(AiEditAnalysisPort::class, new RecordingAnalyzer(new AiAnalysis(conclusion: 'Clean take.')));
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    $decisions = app(AiDecisionProducer::class)->produce(aiContext($edit));
    $review = app(AiCutReviewStorePort::class)->forEdit($edit->id);

    expect($decisions)->toBe([])
        ->and($review?->isResolved())->toBeTrue()
        ->and($review->cuts)->toBe([]);
});

it('never turns a recommendation into a cut', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    runFirstPass($edit);
    $reasons = array_map(
        static fn (AiReviewableCut $cut): string => $cut->reason->value,
        app(AiCutReviewStorePort::class)->forEdit($edit->id)->cuts,
    );

    expect($reasons)->not->toContain('reduce', 'off_script')
        ->and(array_unique($reasons))->toEqualCanonicalizing(['retake', 'pause_marker']);
});

it('stores the recommendations but never the raw response', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    runFirstPass($edit);

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

    try {
        app(AiDecisionProducer::class)->produce($context);
    } catch (CutReviewRequiredException) {
        // The first pass always ends in review when there are proposals.
    }

    expect($stages)->toBe(['ai_analysis']);
});

it('passes the user instructions through as content', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);

    runFirstPass($edit, aiContext($edit, [
        'enabled' => true,
        'consented' => true,
        'instructions' => 'Actúa como mi editor de video senior.',
    ]));

    expect($this->analyzer->receivedInstructions)->toBe('Actúa como mi editor de video senior.');
});

it('deletes the report with its edit', function (): void {
    $edit = VideoEditEloquentModel::factory()->for(VideoEditTestUsers::editor())->create();
    storeTranscriptFor($edit, $this->transcript);
    runFirstPass($edit);

    $edit->delete();

    expect(VideoEditEloquentModel::query()->whereKey($edit->id)->exists())->toBeFalse();
});
