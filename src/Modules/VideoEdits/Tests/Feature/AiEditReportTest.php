<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Domain\Enums\DecisionRejectionReason;
use Modules\VideoEdits\Domain\Ports\AiCutReviewStorePort;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\AiCutReview;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
use Modules\VideoEdits\Domain\ValueObjects\AiReviewableCut;
use Modules\VideoEdits\Domain\ValueObjects\DurationPlan;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditCutDecisionEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->user = VideoEditTestUsers::editor();
    $this->edit = VideoEditEloquentModel::factory()->for($this->user)->completed()->create();

    VideoEditCutDecisionEloquentModel::query()->create([
        'video_edit_id' => $this->edit->id,
        'producer' => 'ai_analyzer',
        'reason' => CutReason::PauseMarker,
        'origin' => DecisionOrigin::Ai,
        'start_ms' => 1_500,
        'end_ms' => 2_100,
        'confidence' => 0.99,
        'evidence' => ['text' => 'PAUSA'],
        'outcome' => DecisionOutcome::Applied,
    ]);

    // A silence cut must not appear in an AI report.
    VideoEditCutDecisionEloquentModel::query()->create([
        'video_edit_id' => $this->edit->id,
        'producer' => 'silence_detector',
        'reason' => CutReason::Silence,
        'origin' => DecisionOrigin::SystemDetection,
        'start_ms' => 5_000,
        'end_ms' => 7_000,
        'outcome' => DecisionOutcome::Applied,
    ]);

    app(AiReportStorePort::class)->store($this->edit->id, new AiAnalysis(
        recommendations: [
            new AiRecommendation(AiRecommendationKind::Reduce, 'Intro runs long', 'Could be 30 seconds.'),
        ],
        conclusion: 'Objectives met.',
    ));
});

it('returns only the AI decisions, never the silence cuts', function (): void {
    $response = $this->actingAs($this->user)
        ->getJson("/data/admin/video-edits/{$this->edit->uuid}/report")
        ->assertOk();

    expect($response->json('decisions'))->toHaveCount(1)
        ->and($response->json('decisions.0.reason'))->toBe('pause_marker')
        ->and($response->json('decisions.0.evidence'))->toBe('PAUSA')
        ->and($response->json('recommendations.0.kind'))->toBe('reduce')
        ->and($response->json('conclusion'))->toBe('Objectives met.');
});

it('reports a rejected AI decision with its reason and a numeric confidence', function (): void {
    VideoEditCutDecisionEloquentModel::query()->create([
        'video_edit_id' => $this->edit->id,
        'producer' => 'ai_analyzer',
        'reason' => CutReason::Retake,
        'origin' => DecisionOrigin::Ai,
        'start_ms' => 9_000,
        'end_ms' => 99_000_000,
        'confidence' => 0.85,
        'outcome' => DecisionOutcome::Rejected,
        'rejection_reason' => DecisionRejectionReason::EndBeyondDuration->value,
    ]);

    $this->actingAs($this->user)
        ->getJson("/data/admin/video-edits/{$this->edit->uuid}/report")
        ->assertOk()
        ->assertJsonCount(2, 'decisions')
        ->assertJsonPath('decisions.0.confidence', 0.99)
        ->assertJsonPath('decisions.1.outcome', 'rejected')
        ->assertJsonPath('decisions.1.rejection_reason', 'end_beyond_duration')
        ->assertJsonPath('decisions.1.evidence', null);
});

it('builds the report from stored data alone', function (): void {
    // Sources are deleted the moment an edit completes (FR-9), so a report that
    // needed them would be unusable exactly when it is wanted (EX-8).
    $this->edit->sources()->delete();

    $this->actingAs($this->user)
        ->getJson("/data/admin/video-edits/{$this->edit->uuid}/report")
        ->assertOk()
        ->assertJsonPath('decisions.0.reason', 'pause_marker');
});

it('renders the report as a PDF from the same data', function (): void {
    $this->actingAs($this->user)
        ->get("/data/admin/video-edits/{$this->edit->uuid}/report?format=pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('rejects an unknown report format instead of silently returning JSON', function (): void {
    $this->actingAs($this->user)
        ->getJson("/data/admin/video-edits/{$this->edit->uuid}/report?format=xlsx")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('format');
});

it('hides another user\'s report behind a 404', function (): void {
    $stranger = VideoEditTestUsers::editor();

    $this->actingAs($stranger)
        ->getJson("/data/admin/video-edits/{$this->edit->uuid}/report")
        ->assertNotFound();
});

it('requires the view permission', function (): void {
    $this->actingAs(VideoEditTestUsers::withoutVideoEditPermissions())
        ->getJson("/data/admin/video-edits/{$this->edit->uuid}/report")
        ->assertForbidden();
});

it('reports an edit that has no AI analysis without failing', function (): void {
    $plain = VideoEditEloquentModel::factory()->for($this->user)->completed()->create();

    $this->actingAs($this->user)
        ->getJson("/data/admin/video-edits/{$plain->uuid}/report")
        ->assertOk()
        ->assertJsonPath('decisions', [])
        ->assertJsonPath('recommendations', [])
        ->assertJsonPath('conclusion', null);
});

it('reports the duration plan and the phrases the request protected', function (): void {
    app(AiReportStorePort::class)->store($this->edit->id, new AiAnalysis(
        conclusion: 'Target reachable.',
        durationPlan: new DurationPlan(
            totalMs: 780_000,
            targetMs: 600_000,
            gapCount: 12,
            deadAirMs: 45_000,
            sentenceGapMs: 30_000,
            intraPhraseGapMs: 15_000,
            silenceRecoverableMs: 60_000,
            aiProposalMs: 140_000,
            plannedCutMs: 140_000,
            longestGaps: [[
                'start_ms' => 12_000,
                'end_ms' => 14_500,
                'gap_ms' => 2_500,
                'after_sentence_end' => true,
            ]],
        ),
        keptPhrases: ['muy bien'],
    ));

    $response = $this->actingAs($this->user)
        ->getJson("/data/admin/video-edits/{$this->edit->uuid}/report")
        ->assertOk();

    expect($response->json('duration_plan.total_ms'))->toBe(780_000)
        ->and($response->json('duration_plan.target_ms'))->toBe(600_000)
        ->and($response->json('duration_plan.required_removal_ms'))->toBe(180_000)
        ->and($response->json('duration_plan.projected_ms'))->toBe(580_000)
        ->and($response->json('duration_plan.verdict'))->toBe('reachable')
        ->and($response->json('duration_plan.longest_gaps.0.gap_ms'))->toBe(2_500)
        ->and($response->json('kept_phrases'))->toBe(['muy bien']);
});

it('renders the plan into the PDF without failing', function (): void {
    app(AiReportStorePort::class)->store($this->edit->id, new AiAnalysis(
        durationPlan: new DurationPlan(
            totalMs: 780_000,
            targetMs: 600_000,
            gapCount: 12,
            deadAirMs: 45_000,
            sentenceGapMs: 30_000,
            intraPhraseGapMs: 15_000,
            silenceRecoverableMs: 60_000,
            aiProposalMs: 140_000,
            plannedCutMs: 140_000,
            longestGaps: [[
                'start_ms' => 12_000,
                'end_ms' => 14_500,
                'gap_ms' => 2_500,
                'after_sentence_end' => true,
            ]],
        ),
        keptPhrases: ['muy bien'],
    ));

    // The plan's table and its gap list are a separate Blade branch; a report
    // that only rendered when there was no plan would fail the moment there was
    // one, which is the only case that matters.
    $this->actingAs($this->user)
        ->get("/data/admin/video-edits/{$this->edit->uuid}/report?format=pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('lists the cuts the review showed, with what remains', function (): void {
    $cut = new AiReviewableCut(
        id: '0199a8a0-0000-7000-8000-000000000001',
        reason: CutReason::Filler,
        startMs: 0,
        endMs: 900,
        confidence: 1.0,
        text: 'este',
        contextBefore: 'Hoy',
        contextAfter: 'vamos',
        explanation: 'Hesitation removed.',
        preselected: true,
        origin: DecisionOrigin::Transcription,
        producer: 'speech_detector',
        keepText: 'Hoy vamos',
    );

    app(AiCutReviewStorePort::class)->store(
        $this->edit->id,
        AiCutReview::pendingAll([$cut])->resolve([$cut->id], new DateTimeImmutable),
    );

    // The PDF is the copy the owner keeps: it has to carry the same rows the
    // review screen showed, including what survives each cut.
    $response = $this->actingAs($this->user)
        ->getJson("/data/admin/video-edits/{$this->edit->uuid}/report")
        ->assertOk();

    expect($response->json('reviewed_cuts.0.keep_text'))->toBe('Hoy vamos')
        ->and($response->json('reviewed_cuts.0.origin'))->toBe('transcription')
        ->and($response->json('reviewed_cuts.0.approved'))->toBeTrue()
        ->and($response->json('review_includes_automatic'))->toBeTrue();
});

it('reports an edit without a plan without inventing one', function (): void {
    $plain = VideoEditEloquentModel::factory()->for($this->user)->completed()->create();

    $this->actingAs($this->user)
        ->getJson("/data/admin/video-edits/{$plain->uuid}/report")
        ->assertOk()
        ->assertJsonPath('duration_plan', null)
        ->assertJsonPath('kept_phrases', []);
});
