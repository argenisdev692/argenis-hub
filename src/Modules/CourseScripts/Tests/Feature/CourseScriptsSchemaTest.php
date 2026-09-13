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
use Modules\CourseScripts\Domain\Enums\VideoOutcomeStatus;
use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;
use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseDeliverableEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseResearchFindingEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseScriptVersionEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoOutcomeEloquentModel;

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
        ->and($course->user->courses()->count())->toBe(1);
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

it('declares the parent side of every foreign key', function (): void {
    $course = CourseEloquentModel::factory()->withVideos(1)->create();
    $video = $course->videos()->firstOrFail();
    $block = $course->blocks()->firstOrFail();

    $run = courseRun($course, GenerationRunStatus::Running);
    $run->update(['scoped_block_id' => $block->id, 'current_video_id' => $video->id]);
    CourseVideoOutcomeEloquentModel::query()->create([
        'course_generation_run_id' => $run->id,
        'course_video_id' => $video->id,
        'position' => 0,
        'status' => VideoOutcomeStatus::Pending,
    ]);

    $version = scriptVersion($video->id, 1);
    $version->update(['course_generation_run_id' => $run->id]);

    $finding = CourseResearchFindingEloquentModel::query()->create([
        'course_id' => $course->id,
        'course_video_id' => $video->id,
        'provider' => 'tavily',
        'query' => 'logistics',
        'url' => 'https://example.com/a',
        'title' => 'A source',
        'content' => 'Body',
        'gathered_at' => now(),
    ]);
    $version->sources()->attach($finding->id);

    expect($block->scopedRuns()->count())->toBe(1)
        ->and($video->outcomes()->count())->toBe(1)
        ->and($video->currentRuns()->count())->toBe(1)
        ->and($run->scriptVersions()->count())->toBe(1)
        ->and($finding->scriptVersions()->pluck('course_script_versions.id')->all())->toBe([$version->id]);
});

it('accumulates run and outcome call counters atomically', function (): void {
    $course = CourseEloquentModel::factory()->withVideos(2)->create();
    [$first, $second] = $course->videos()->orderBy('number')->pluck('id')->all();
    $runs = app(GenerationRunRepositoryPort::class);

    $run = $runs->create([
        'course_id' => $course->id,
        'user_id' => $course->user_id,
        'kind' => GenerationRunKind::Generation,
        'scope' => GenerationScope::Course,
        'writer_provider' => 'openai',
        'with_review' => true,
        'status' => GenerationRunStatus::Running,
        'ai_call_ceiling' => 900,
        'research_call_ceiling' => 250,
    ], [$first, $second]);

    $runs->startOutcome($run->id, $first);
    $runs->startOutcome($run->id, $first);
    $runs->completeOutcome($run->id, $first, new CallUsage(aiWrite: 3, aiReview: 1, research: 2), 1);
    $runs->startOutcome($run->id, $second);
    $runs->failOutcome($run->id, $second, 'provider_error', new CallUsage(aiWrite: 1));
    $run = $runs->addUsage($run->id, new CallUsage(aiWrite: 4, aiReview: 1, research: 2));
    $run = $runs->addUsage($run->id, new CallUsage(aiWrite: 1));

    $completed = CourseVideoOutcomeEloquentModel::query()->where('course_video_id', $first)->firstOrFail();

    expect($run->ai_write_calls_consumed)->toBe(5)
        ->and($run->ai_review_calls_consumed)->toBe(1)
        ->and($run->research_calls_consumed)->toBe(2)
        ->and($run->videos_completed)->toBe(1)
        ->and($run->videos_failed)->toBe(1)
        ->and($run->current_video_id)->toBeNull()
        ->and($completed->attempts)->toBe(2)
        ->and($completed->status)->toBe(VideoOutcomeStatus::Completed)
        ->and($completed->ai_write_calls)->toBe(3)
        ->and($completed->research_calls)->toBe(2);
});

it('soft deletes a course and cascades children on force delete', function (): void {
    $course = CourseEloquentModel::factory()->withVideos(2)->create();

    $course->delete();

    expect(CourseEloquentModel::query()->find($course->id))->toBeNull()
        ->and(CourseEloquentModel::withTrashed()->find($course->id))->not->toBeNull();

    $course->forceDelete();

    expect(DB::table('course_videos')->where('course_id', $course->id)->count())->toBe(0);
});
