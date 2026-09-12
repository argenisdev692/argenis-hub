<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\VideoEdits\Application\DTOs\VideoEditFilterData;
use Modules\VideoEdits\Application\Support\VideoEditFilterSummary;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Infrastructure\Http\Export\VideoEditExportTransformer;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditSourceEloquentModel;
use Modules\VideoEdits\Tests\Support\VideoEditTestUsers;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Streamed responses only run their callback when sent, so the body has to be
 * captured rather than read off the response object.
 */
function exportBody(Response $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('streams the caller\'s history as CSV', function (): void {
    $user = VideoEditTestUsers::editor();
    $edit = VideoEditEloquentModel::factory()->for($user)->completed()->create();
    VideoEditSourceEloquentModel::factory()->count(2)->sequence(['position' => 1], ['position' => 2])
        ->for($edit, 'videoEdit')->create();

    $response = $this->actingAs($user)->get('/data/admin/video-edits/export?format=csv');

    $response->assertOk();
    $body = exportBody($response->baseResponse);

    expect($body)->toContain('Reference', 'Mode', 'Status', 'Clips', 'Cuts')
        ->and($body)->toContain($edit->uuid)
        ->and($body)->toContain('Completed');
});

it('never exports another user\'s edits', function (): void {
    $user = VideoEditTestUsers::editor();
    $mine = VideoEditEloquentModel::factory()->for($user)->completed()->create();
    $theirs = VideoEditEloquentModel::factory()->completed()->create();

    $response = $this->actingAs($user)->get('/data/admin/video-edits/export?format=csv');
    $body = exportBody($response->baseResponse);

    expect($body)->toContain($mine->uuid)
        ->and($body)->not->toContain($theirs->uuid);
});

it('never exports drafts', function (): void {
    $user = VideoEditTestUsers::editor();
    $draft = VideoEditEloquentModel::factory()->for($user)->create(['status' => VideoEditStatus::Draft]);
    $listed = VideoEditEloquentModel::factory()->for($user)->completed()->create();

    $body = exportBody($this->actingAs($user)->get('/data/admin/video-edits/export?format=csv')->baseResponse);

    expect($body)->toContain($listed->uuid)
        ->and($body)->not->toContain($draft->uuid);
});

it('applies the same filters the history list uses', function (): void {
    $user = VideoEditTestUsers::editor();
    $completed = VideoEditEloquentModel::factory()->for($user)->completed()->create();
    $failed = VideoEditEloquentModel::factory()->for($user)->failed()->create();

    $body = exportBody(
        $this->actingAs($user)->get('/data/admin/video-edits/export?format=csv&status=failed')->baseResponse,
    );

    expect($body)->toContain($failed->uuid)
        ->and($body)->not->toContain($completed->uuid);
});

it('filters by an inclusive date range', function (): void {
    $user = VideoEditTestUsers::editor();
    $inside = VideoEditEloquentModel::factory()->for($user)->completed()
        ->create(['created_at' => '2026-03-15 23:30:00']);
    $outside = VideoEditEloquentModel::factory()->for($user)->completed()
        ->create(['created_at' => '2026-03-16 00:30:00']);

    $body = exportBody(
        $this->actingAs($user)
            ->get('/data/admin/video-edits/export?format=csv&date_from=2026-03-01&date_to=2026-03-15')
            ->baseResponse,
    );

    // The last day of the range is included down to 23:59:59 (BACKEND-PHP §5.2).
    expect($body)->toContain($inside->uuid)
        ->and($body)->not->toContain($outside->uuid);
});

it('renders a PDF report', function (): void {
    $user = VideoEditTestUsers::editor();
    VideoEditEloquentModel::factory()->for($user)->completed()->create();

    $response = $this->actingAs($user)->get('/data/admin/video-edits/export?format=pdf');

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('rejects an unknown export format', function (): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->get('/data/admin/video-edits/export?format=exe')
        ->assertStatus(422);
});

it('rejects a sort field that is not on the allow-list', function (): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->getJson('/data/admin/video-edits/export?format=csv&sort_field=result_path')
        ->assertStatus(422);
});

it('rejects an inverted date range', function (): void {
    $this->actingAs(VideoEditTestUsers::editor())
        ->getJson('/data/admin/video-edits/export?format=csv&date_from=2026-03-20&date_to=2026-03-01')
        ->assertStatus(422);
});

it('requires the export permission', function (): void {
    $this->actingAs(VideoEditTestUsers::withoutVideoEditPermissions())
        ->get('/data/admin/video-edits/export?format=csv')
        ->assertForbidden();
});

it('requires authentication', function (): void {
    $this->get('/data/admin/video-edits/export?format=csv')->assertRedirect();
});

it('formats durations and dates for a human reader', function (): void {
    $edit = VideoEditEloquentModel::factory()->make([
        'uuid' => 'a1b2c3d4-0000-7000-8000-000000000000',
        'mode' => VideoEditMode::AutoEdit,
        'status' => VideoEditStatus::Completed,
        'original_duration_ms' => 3_725_000,   // 1:02:05
        'final_duration_ms' => 125_000,        // 2:05
        'removed_duration_ms' => null,
        'applied_cut_count' => 7,
        'created_at' => '2026-03-03 10:00:00',
        'completed_at' => null,
    ]);

    $row = VideoEditExportTransformer::transformForExcel($edit);

    expect($row['Original'])->toBe('1:02:05')
        ->and($row['Final'])->toBe('2:05')
        ->and($row['Mode'])->toBe('Auto edit')
        ->and($row['Status'])->toBe('Completed')
        ->and($row['Cuts'])->toBe('7')
        ->and($row['Created'])->toBe('March 3, 2026')
        // An edit that never finished has nothing to report, and an em dash
        // says that where "0:00" would read as a zero-length video.
        ->and($row['Removed'])->toBe('—')
        ->and($row['Completed'])->toBe('—');
});

it('describes the filters a report was run with', function (): void {
    expect(VideoEditFilterSummary::describe(new VideoEditFilterData))
        ->toBe('Every edit in your history.');

    expect(VideoEditFilterSummary::describe(new VideoEditFilterData(
        status: VideoEditStatus::Failed,
        mode: VideoEditMode::AutoEdit,
        dateFrom: '2026-03-01',
        dateTo: '2026-03-31',
    )))->toBe('Failed only · auto edit mode · created March 1, 2026 – March 31, 2026.');

    expect(VideoEditFilterSummary::describe(new VideoEditFilterData(dateFrom: '2026-03-01')))
        ->toBe('Created from March 1, 2026.');
});
