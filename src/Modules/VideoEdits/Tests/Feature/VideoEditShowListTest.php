<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('lists only the caller\'s submitted edits, newest first', function (): void {
    $user = VideoEditTestUsers::editor();
    $older = VideoEditEloquentModel::factory()->for($user)->completed()->create(['created_at' => now()->subHours(2)]);
    VideoEditSourceEloquentModel::factory()->count(2)->sequence(['position' => 1], ['position' => 2])->for($older, 'videoEdit')->create();
    $newer = VideoEditEloquentModel::factory()->for($user)->failed()->create(['created_at' => now()->subHour()]);
    VideoEditEloquentModel::factory()->for($user)->create(); // draft — never listed
    VideoEditEloquentModel::factory()->completed()->create(); // someone else's

    $this->actingAs($user)
        ->getJson('/data/admin/video-edits')
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('data.0.uuid', $newer->uuid)
        ->assertJsonPath('data.0.status', 'failed')
        ->assertJsonPath('data.1.uuid', $older->uuid)
        ->assertJsonPath('data.1.source_count', 2)
        ->assertJsonPath('data.1.final_duration_ms', 540_000);
});

it('filters the history and paginates it', function (): void {
    $user = VideoEditTestUsers::editor();
    VideoEditEloquentModel::factory()->count(3)->for($user)->completed()->create();
    VideoEditEloquentModel::factory()->for($user)->failed()->create();

    $this->actingAs($user)
        ->getJson('/data/admin/video-edits?status=completed&per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonPath('per_page', 2)
        ->assertJsonCount(1, 'data');
});

it('rejects unknown filters and oversized pages', function (string $query, string $error): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->getJson("/data/admin/video-edits?{$query}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'drafts are not a listed status' => ['status=draft', 'status'],
    'more than 100 per page' => ['per_page=101', 'per_page'],
]);

it('shows the full detail of one edit, including what a re-edit would reuse', function (): void {
    $user = VideoEditTestUsers::editor();
    $original = VideoEditEloquentModel::factory()->for($user)->completed()->create();
    $edit = VideoEditEloquentModel::factory()->for($user)->completed()->create([
        'previous_edit_id' => $original->id,
        'parameters' => [
            'silence_removal' => ['enabled' => true, 'threshold_seconds' => 2.5],
            'manual_ranges' => [['start_ms' => 1_000, 'end_ms' => 2_000, 'note' => 'retake']],
        ],
        'warnings' => ['Clip 2 has no audio; silence was added for its duration.'],
    ]);
    VideoEditSourceEloquentModel::factory()->probed()->purged()->for($edit, 'videoEdit')->create(['original_name' => 'take-1.mp4']);
    $edit->appliedCuts()->create(['sequence' => 1, 'start_ms' => 1_000, 'end_ms' => 2_000, 'reasons' => ['manual'], 'origins' => ['user']]);
    $edit->cutDecisions()->create([
        'producer' => 'manual', 'reason' => CutReason::Manual, 'origin' => DecisionOrigin::User,
        'start_ms' => 1_000, 'end_ms' => 2_000, 'outcome' => DecisionOutcome::Applied, 'applied_cut_sequence' => 1,
    ]);

    $response = $this->actingAs($user)->getJson("/data/admin/video-edits/{$edit->uuid}");

    $response->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('previous_edit_uuid', $original->uuid)
        ->assertJsonPath('parameters.silence_removal.threshold_seconds', 2.5)
        ->assertJsonPath('parameters.manual_ranges.0.note', 'retake')
        ->assertJsonPath('sources.0.original_name', 'take-1.mp4')
        ->assertJsonPath('sources.0.available', false)
        ->assertJsonPath('summary.final_duration_ms', 540_000)
        ->assertJsonPath('summary.removed_duration_ms', 60_000)
        ->assertJsonPath('applied_cuts.0.duration_ms', 1_000)
        ->assertJsonPath('applied_cuts.0.reasons', ['manual'])
        ->assertJsonPath('decisions.0.outcome', 'applied')
        ->assertJsonPath('warnings.0', 'Clip 2 has no audio; silence was added for its duration.')
        ->assertJsonPath('failure', null)
        ->assertJsonPath('can_download', true)
        ->assertJsonPath('can_retry', false)
        ->assertJsonPath('can_delete', true);

    expect($response->getContent())->not->toContain('result_path')
        ->and($response->getContent())->not->toContain('storage_path')
        ->and($response->getContent())->not->toContain('video-edits/tests');
});

it('exposes the retry window and the failure of a failed edit', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->invalidCutRanges()->create();
    VideoEditSourceEloquentModel::factory()->for($edit, 'videoEdit')->create();

    $this->actingAs($user)
        ->getJson("/data/admin/video-edits/{$edit->uuid}")
        ->assertOk()
        ->assertJsonPath('failure.code', 'invalid_cut_ranges')
        ->assertJsonPath('failure.details.manual_ranges.0.error', 'end_beyond_duration')
        ->assertJsonPath('can_retry', true)
        ->assertJsonPath('retry_available_until', fn (?string $value): bool => $value !== null)
        ->assertJsonPath('can_download', false);
});

it('hides edits owned by someone else', function (): void {
    $edit = VideoEditEloquentModel::factory()->completed()->create();

    $this->actingAs(VideoEditTestUsers::editor())
        ->getJson("/data/admin/video-edits/{$edit->uuid}")
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});
