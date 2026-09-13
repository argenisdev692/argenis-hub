<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Tests\Support\CourseScriptTestUsers;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->author = CourseScriptTestUsers::author();
});

/**
 * Streamed responses only run their callback when sent, so the body has to be
 * captured rather than read off the response object.
 */
function courseExportBody(Response $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

describe('restore', function (): void {
    it('restores the author\'s soft-deleted course', function (): void {
        $course = CourseEloquentModel::factory()->create(['user_id' => $this->author->id]);
        $course->delete();

        $this->actingAs($this->author)->postJson(route('course-scripts.restore', $course->uuid))->assertNoContent();

        expect($course->fresh()->deleted_at)->toBeNull();
    });

    it('treats another author\'s course as missing', function (): void {
        $course = CourseEloquentModel::factory()->create();
        $course->delete();

        $this->actingAs($this->author)->postJson(route('course-scripts.restore', $course->uuid))->assertNotFound();

        expect($course->fresh()->deleted_at)->not->toBeNull();
    });

    it('requires the restore permission', function (): void {
        $user = CourseScriptTestUsers::withoutPermissions();
        $course = CourseEloquentModel::factory()->create(['user_id' => $user->id]);
        $course->delete();

        $this->actingAs($user)->postJson(route('course-scripts.restore', $course->uuid))->assertForbidden();
    });
});

describe('bulk actions', function (): void {
    it('soft deletes only the author\'s selected courses and audits each one', function (): void {
        $mine = CourseEloquentModel::factory()->count(2)->create(['user_id' => $this->author->id]);
        $theirs = CourseEloquentModel::factory()->create();

        $this->actingAs($this->author)
            ->postJson(route('course-scripts.bulk-delete'), ['uuids' => [...$mine->pluck('uuid')->all(), $theirs->uuid]])
            ->assertOk()
            ->assertJsonPath('data.affected', 2);

        expect(CourseEloquentModel::onlyTrashed()->whereIn('uuid', $mine->pluck('uuid'))->count())->toBe(2)
            ->and($theirs->fresh()->deleted_at)->toBeNull()
            ->and(Activity::query()->where(fn ($query) => $query->where('event', 'course_scripts.course_deleted')->orWhere('description', 'course_scripts.course_deleted'))->count())->toBe(2);
    });

    it('restores only the author\'s selected courses', function (): void {
        $mine = CourseEloquentModel::factory()->create(['user_id' => $this->author->id]);
        $theirs = CourseEloquentModel::factory()->create();
        $mine->delete();
        $theirs->delete();

        $this->actingAs($this->author)
            ->postJson(route('course-scripts.bulk-restore'), ['uuids' => [$mine->uuid, $theirs->uuid]])
            ->assertOk()
            ->assertJsonPath('data.affected', 1);

        expect($mine->fresh()->deleted_at)->toBeNull()
            ->and($theirs->fresh()->deleted_at)->not->toBeNull();
    });

    it('validates the selection', function (array $payload, string $errorKey): void {
        $this->actingAs($this->author)->postJson(route('course-scripts.bulk-delete'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorKey);
    })->with([
        'empty' => [['uuids' => []], 'uuids'],
        'not uuids' => [['uuids' => ['not-a-uuid']], 'uuids.0'],
        'over the cap' => [['uuids' => array_map(static fn (): string => (string) str()->uuid7(), range(1, 501))], 'uuids'],
    ]);

    it('guards bulk delete and bulk restore by permission', function (): void {
        $user = CourseScriptTestUsers::withoutPermissions();
        $uuid = (string) str()->uuid7();

        $this->actingAs($user)->postJson(route('course-scripts.bulk-delete'), ['uuids' => [$uuid]])->assertForbidden();
        $this->actingAs($user)->postJson(route('course-scripts.bulk-restore'), ['uuids' => [$uuid]])->assertForbidden();
    });
});

it('lists the recovery bin with the trashed filter', function (): void {
    $live = CourseEloquentModel::factory()->create(['user_id' => $this->author->id]);
    $deleted = CourseEloquentModel::factory()->create(['user_id' => $this->author->id]);
    $deleted->delete();

    $this->actingAs($this->author)->getJson(route('course-scripts.index', ['trashed' => 'only']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $deleted->uuid)
        ->assertJsonPath('data.0.deleted_at', fn (?string $value): bool => $value !== null);

    $this->actingAs($this->author)->getJson(route('course-scripts.index'))
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $live->uuid)
        ->assertJsonPath('data.0.deleted_at', null);

    $this->actingAs($this->author)->getJson(route('course-scripts.index', ['trashed' => 'bogus']))->assertUnprocessable();
});

describe('export', function (): void {
    it('streams the author\'s courses as CSV with soft-delete and generation status', function (): void {
        $live = CourseEloquentModel::factory()->withVideos(2)->create(['user_id' => $this->author->id, 'title' => 'Curso vivo']);
        $deleted = CourseEloquentModel::factory()->create(['user_id' => $this->author->id, 'status' => CourseStatus::PartiallyGenerated]);
        $deleted->delete();

        $response = $this->actingAs($this->author)->get(route('course-scripts.export', ['format' => 'csv', 'trashed' => 'with']));

        $response->assertOk();
        $body = courseExportBody($response->baseResponse);

        expect($body)->toContain('Reference', 'Generation Status', 'Status', 'Created')
            ->and($body)->toContain($live->uuid, 'Curso vivo', 'Active')
            ->and($body)->toContain($deleted->uuid, 'Suspended', 'Partially generated')
            ->and($body)->toContain($live->created_at->format('F j, Y'));
    });

    it('never exports another author\'s courses', function (): void {
        $mine = CourseEloquentModel::factory()->create(['user_id' => $this->author->id]);
        $theirs = CourseEloquentModel::factory()->create();

        $body = courseExportBody($this->actingAs($this->author)->get(route('course-scripts.export'))->baseResponse);

        expect($body)->toContain($mine->uuid)->and($body)->not->toContain($theirs->uuid);
    });

    it('applies the list filters', function (): void {
        $ready = CourseEloquentModel::factory()->create(['user_id' => $this->author->id, 'status' => CourseStatus::Ready]);
        $completed = CourseEloquentModel::factory()->create(['user_id' => $this->author->id, 'status' => CourseStatus::Completed]);

        $body = courseExportBody($this->actingAs($this->author)->get(route('course-scripts.export', ['status' => 'completed']))->baseResponse);

        expect($body)->toContain($completed->uuid)->and($body)->not->toContain($ready->uuid);
    });

    it('renders XLSX and PDF downloads', function (): void {
        CourseEloquentModel::factory()->create(['user_id' => $this->author->id]);

        $xlsx = $this->actingAs($this->author)->get(route('course-scripts.export', ['format' => 'xlsx']));
        $xlsx->assertOk();
        expect($xlsx->headers->get('Content-Type'))->toContain('spreadsheetml');

        $pdf = $this->actingAs($this->author)->get(route('course-scripts.export', ['format' => 'pdf']));
        $pdf->assertOk();
        expect($pdf->headers->get('Content-Type'))->toContain('pdf');
    });

    it('rejects an unknown format and unauthorised users', function (): void {
        $this->actingAs($this->author)->getJson(route('course-scripts.export', ['format' => 'docx']))->assertUnprocessable();

        $this->actingAs(CourseScriptTestUsers::withoutPermissions())->get(route('course-scripts.export'))->assertForbidden();
    });
});
