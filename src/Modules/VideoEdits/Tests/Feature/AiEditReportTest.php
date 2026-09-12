<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Domain\Ports\AiReportStorePort;
use Modules\VideoEdits\Domain\ValueObjects\AiAnalysis;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
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
