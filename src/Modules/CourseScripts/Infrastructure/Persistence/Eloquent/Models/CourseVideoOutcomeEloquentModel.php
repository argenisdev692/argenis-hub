<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Domain\Enums\VideoOutcomeStatus;

/**
 * @internal
 *
 * Per-video truth of a run (FR-18). Framework batch rows are transport only.
 *
 * @property int $id
 * @property int $course_generation_run_id
 * @property int $course_video_id
 * @property int $position
 * @property VideoOutcomeStatus $status
 * @property string|null $failure_reason
 * @property int $attempts
 * @property int $review_iterations
 * @property int $ai_write_calls
 * @property int $ai_review_calls
 * @property int $research_calls
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseGenerationRunEloquentModel $run
 * @property-read CourseVideoEloquentModel $video
 *
 * @mixin \Eloquent
 */
#[Table('course_video_outcomes')]
#[Fillable([
    'course_generation_run_id',
    'course_video_id',
    'position',
    'status',
    'failure_reason',
    'attempts',
    'review_iterations',
    'ai_write_calls',
    'ai_review_calls',
    'research_calls',
    'started_at',
    'finished_at',
])]
final class CourseVideoOutcomeEloquentModel extends Model
{
    /** @var list<string> */
    protected $hidden = ['id', 'course_generation_run_id', 'course_video_id'];

    /** @var array<string, int> */
    protected $attributes = [
        'attempts' => 0,
        'review_iterations' => 0,
        'ai_write_calls' => 0,
        'ai_review_calls' => 0,
        'research_calls' => 0,
    ];

    /**
     * @return BelongsTo<CourseGenerationRunEloquentModel, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CourseGenerationRunEloquentModel::class, 'course_generation_run_id');
    }

    /**
     * @return BelongsTo<CourseVideoEloquentModel, $this>
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(CourseVideoEloquentModel::class, 'course_video_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'course_generation_run_id' => 'integer',
            'course_video_id' => 'integer',
            'position' => 'integer',
            'status' => VideoOutcomeStatus::class,
            'attempts' => 'integer',
            'review_iterations' => 'integer',
            'ai_write_calls' => 'integer',
            'ai_review_calls' => 'integer',
            'research_calls' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
