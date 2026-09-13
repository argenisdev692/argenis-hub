<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\CourseScripts\Domain\Enums\DeliverableDocumentType;
use Modules\CourseScripts\Domain\Enums\DeliverableFormat;
use Modules\CourseScripts\Domain\Enums\GenerationRunKind;
use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Enums\GenerationScope;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseDeliverableEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;

uses(RefreshDatabase::class);

/**
 * The persisted shape of plan §4: relations both ways, database-level
 * invariants, public UUIDv7 identifiers.
 */
function courseRun(CourseEloquentModel $course, GenerationRunStatus $status): CourseGenerationRunEloquentModel
{
    return CourseGenerationRunEloquentModel::query()->create([
        'course_id' => $course->id,
        'user_id' => $course->user_id,
        'kind' => GenerationRunKind::Generation,
        'scope' => GenerationScope::Course,
        'writer_provider' => 'openai',
        'with_review' => true,
        'status' => $status,
        'ai_call_ceiling' => 900,
        'research_call_ceiling' => 250,
    ]);
}

function scriptVersion(int $videoId, int $version): CourseScriptVersionEloquentModel
{
    return CourseScriptVersionEloquentModel::query()->create([
        'course_video_id' => $videoId,
        'version' => $version,
        'writer_provider' => 'openai',
        'brief_revision' => 1,
        'bible_revision' => 0,
        'technical_header' => [],
        'learning_objectives' => [],
        'continuity_note' => '',
        'continuity_source_video_ids' => [],
        'sections' => [],
        'taught_summary' => '',
        'summary_points' => [],
        'recording_notes' => [],
        'verification_checklist' => [],
        'coverage_map' => [],
        'errors_check' => [],
        'notes_excerpt_ids' => [],
    ]);
}

it('persists a course with blocks and videos in course order', function (): void {
    $course = CourseEloquentModel::factory()->withVideos(3)->create();

    expect($course->uuid)->toMatch('/^[0-9a-f-]{36}$/')
        ->and($course->videos()->orderBy('number')->pluck('number')->all())->toBe([1, 2, 3])
        ->and($course->blocks)->toHaveCount(1)
        ->and($course->user->scriptCourses()->count())->toBe(1);
});

it('allows only one active run per course', function (): void {
    $course = CourseEloquentModel::factory()->create();

    courseRun($course, GenerationRunStatus::Running);

    expect(fn () => courseRun($course, GenerationRunStatus::Queued))->toThrow(QueryException::class);
});

it('allows a new run once the previous one is finished', function (): void {
    $course = CourseEloquentModel::factory()->create();

    courseRun($course, GenerationRunStatus::Completed);
    $run = courseRun($course, GenerationRunStatus::Queued);

    expect($run->with_review)->toBeTrue()
        ->and(User::query()->find($course->user_id)->courseGenerationRuns()->count())->toBe(2);
});

it('keeps version numbers unique per video', function (): void {
    $course = CourseEloquentModel::factory()->withVideos(1)->create();
    $videoId = $course->videos()->value('id');

    scriptVersion($videoId, 1);

    expect(fn () => scriptVersion($videoId, 1))->toThrow(QueryException::class);
});

it('keeps one deliverable per version, document, practice file and format', function (): void {
    $course = CourseEloquentModel::factory()->withVideos(1)->create();
    $version = scriptVersion($course->videos()->value('id'), 1);

    $deliverable = static fn (DeliverableDocumentType $type, string $fileName = '') => CourseDeliverableEloquentModel::query()->create([
        'course_script_version_id' => $version->id,
        'document_type' => $type,
        'artifact_file_name' => $fileName,
        'format' => DeliverableFormat::Pdf,
        'path' => 'course-scripts/x.pdf',
        'size_bytes' => 10,
        'checksum' => str_repeat('a', 64),
    ]);

    $deliverable(DeliverableDocumentType::Script);
    $deliverable(DeliverableDocumentType::PracticeFile, 'Propuesta_Logistica_ProveedorA_2026');
    $deliverable(DeliverableDocumentType::PracticeFile, 'Propuesta_Logistica_ProveedorB_2026');

    expect(fn () => $deliverable(DeliverableDocumentType::Script))->toThrow(QueryException::class)
        ->and($version->deliverables()->count())->toBe(3);
});

it('soft deletes a course and cascades children on force delete', function (): void {
    $course = CourseEloquentModel::factory()->withVideos(2)->create();

    $course->delete();

    expect(CourseEloquentModel::query()->find($course->id))->toBeNull()
        ->and(CourseEloquentModel::withTrashed()->find($course->id))->not->toBeNull();

    $course->forceDelete();

    expect(DB::table('course_videos')->where('course_id', $course->id)->count())->toBe(0);
});
