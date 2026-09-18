<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;

/**
 * One generated script (US-5). Every part of the reference format is stored as
 * structure; Markdown, PDF and the prompts sheet are renderings (DEC-3).
 *
 * @internal
 *
 * @property int $id
 * @property string $uuid
 * @property int $course_video_id
 * @property int|null $course_generation_run_id
 * @property int $version
 * @property bool $is_accepted
 * @property string $writer_provider
 * @property int $brief_revision
 * @property int $bible_revision
 * @property array<string, mixed> $technical_header
 * @property list<string> $learning_objectives
 * @property string $continuity_note
 * @property bool $continuity_is_provisional
 * @property list<int> $continuity_source_video_ids
 * @property bool $continuity_stale
 * @property list<array<string, mixed>> $sections
 * @property bool $uses_tool
 * @property string $taught_summary
 * @property list<string> $summary_points
 * @property array{number: int, title: string}|null $next_video
 * @property array<string, mixed> $recording_notes
 * @property list<string> $verification_checklist
 * @property array{title: string, scenario: string, task: string, success_criteria: list<string>}|null $practice_exercise
 * @property array<string, list<string>> $coverage_map
 * @property array<string, mixed> $errors_check
 * @property bool $is_grounded
 * @property list<string> $notes_excerpt_ids
 * @property string|null $prompts_sheet_reason
 * @property bool $practice_warranted
 * @property string|null $practice_decision_reason
 * @property bool $reviewed
 * @property string|null $reviewer_provider
 * @property array<string, int>|null $review_scores
 * @property list<array{target: string, text: string}>|null $review_objections
 * @property int $review_iterations
 * @property bool|null $passed_review
 * @property string|null $feedback_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseVideoEloquentModel $video
 * @property-read CourseGenerationRunEloquentModel|null $run
 * @property-read CoursePracticeVersionEloquentModel|null $practice
 * @property-read Collection<int, CourseDeliverableEloquentModel> $deliverables
 * @property-read Collection<int, CourseResearchFindingEloquentModel> $sources
 * @property-read int|null $deliverables_count
 * @property-read int|null $sources_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereBibleRevision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereBriefRevision($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereContinuityIsProvisional($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereContinuityNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereContinuitySourceVideoIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereContinuityStale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereCourseGenerationRunId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereCourseVideoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereCoverageMap($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereErrorsCheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereFeedbackNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereIsAccepted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereIsGrounded($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereLearningObjectives($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereNextVideo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereNotesExcerptIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel wherePassedReview($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel wherePracticeDecisionReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel wherePracticeExercise($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel wherePracticeWarranted($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel wherePromptsSheetReason($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereRecordingNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereReviewIterations($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereReviewObjections($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereReviewScores($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereReviewed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereReviewerProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereSections($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereSummaryPoints($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereTaughtSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereTechnicalHeader($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereUsesTool($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereVerificationChecklist($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereVersion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseScriptVersionEloquentModel whereWriterProvider($value)
 *
 * @mixin \Eloquent
 */
#[Table('course_script_versions')]
#[Fillable([
    'uuid',
    'course_video_id',
    'course_generation_run_id',
    'version',
    'is_accepted',
    'writer_provider',
    'brief_revision',
    'bible_revision',
    'technical_header',
    'learning_objectives',
    'continuity_note',
    'continuity_is_provisional',
    'continuity_source_video_ids',
    'continuity_stale',
    'sections',
    'uses_tool',
    'taught_summary',
    'summary_points',
    'next_video',
    'recording_notes',
    'verification_checklist',
    'practice_exercise',
    'coverage_map',
    'errors_check',
    'is_grounded',
    'notes_excerpt_ids',
    'prompts_sheet_reason',
    'practice_warranted',
    'practice_decision_reason',
    'reviewed',
    'reviewer_provider',
    'review_scores',
    'review_objections',
    'review_iterations',
    'passed_review',
    'feedback_note',
])]
final class CourseScriptVersionEloquentModel extends Model
{
    use GeneratesPublicUuid;

    /** @var list<string> */
    protected $hidden = ['id', 'course_video_id', 'course_generation_run_id', 'continuity_source_video_ids'];

    /**
     * @return BelongsTo<CourseVideoEloquentModel, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(CourseVideoEloquentModel::class, 'course_video_id');
    }

    /**
     * @return BelongsTo<CourseGenerationRunEloquentModel, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CourseGenerationRunEloquentModel::class, 'course_generation_run_id');
    }

    /**
     * @return HasOne<CoursePracticeVersionEloquentModel, $this>
     */
    public function practice(): HasOne
    {
        return $this->hasOne(CoursePracticeVersionEloquentModel::class, 'course_script_version_id');
    }

    /**
     * @return HasMany<CourseDeliverableEloquentModel, $this>
     */
    public function deliverables(): HasMany
    {
        return $this->hasMany(CourseDeliverableEloquentModel::class, 'course_script_version_id');
    }

    /**
     * @return BelongsToMany<CourseResearchFindingEloquentModel, $this>
     */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            CourseResearchFindingEloquentModel::class,
            'course_script_sources',
            'course_script_version_id',
            'course_research_finding_id',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'course_video_id' => 'integer',
            'course_generation_run_id' => 'integer',
            'version' => 'integer',
            'is_accepted' => 'boolean',
            'brief_revision' => 'integer',
            'bible_revision' => 'integer',
            'technical_header' => 'array',
            'learning_objectives' => 'array',
            'continuity_is_provisional' => 'boolean',
            'continuity_source_video_ids' => 'array',
            'continuity_stale' => 'boolean',
            'sections' => 'array',
            'uses_tool' => 'boolean',
            'summary_points' => 'array',
            'next_video' => 'array',
            'recording_notes' => 'array',
            'verification_checklist' => 'array',
            'practice_exercise' => 'array',
            'coverage_map' => 'array',
            'errors_check' => 'array',
            'is_grounded' => 'boolean',
            'notes_excerpt_ids' => 'array',
            'practice_warranted' => 'boolean',
            'reviewed' => 'boolean',
            'review_scores' => 'array',
            'review_objections' => 'array',
            'review_iterations' => 'integer',
            'passed_review' => 'boolean',
        ];
    }
}
