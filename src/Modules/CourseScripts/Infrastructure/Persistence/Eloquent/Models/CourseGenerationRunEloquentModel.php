<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Domain\Enums\GenerationRunKind;
use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Enums\GenerationScope;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @internal
 *
 * One generation run (US-9, US-13). `with_review` is the author's per-run
 * choice of the independent second review (DEC-10).
 *
 * @property int $id
 * @property string $uuid
 * @property int $course_id
 * @property int $user_id
 * @property GenerationRunKind $kind
 * @property GenerationScope $scope
 * @property int|null $scoped_block_id
 * @property string $writer_provider
 * @property string|null $reviewer_provider
 * @property bool $with_review
 * @property bool $reviewer_not_independent
 * @property GenerationRunStatus $status
 * @property string|null $batch_id
 * @property int $estimated_ai_write_calls
 * @property int $estimated_ai_review_calls
 * @property int $estimated_research_calls
 * @property int $ai_call_ceiling
 * @property int $research_call_ceiling
 * @property int $ai_write_calls_consumed
 * @property int $ai_review_calls_consumed
 * @property int $research_calls_consumed
 * @property int $videos_total
 * @property int $videos_completed
 * @property int $videos_failed
 * @property int|null $current_video_id
 * @property string|null $stop_reason
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseEloquentModel $course
 * @property-read User $user
 * @property-read CourseBlockEloquentModel|null $scopedBlock
 * @property-read CourseVideoEloquentModel|null $currentVideo
 * @property-read Collection<int, CourseVideoOutcomeEloquentModel> $outcomes
 *
 * @mixin \Eloquent
 */
#[Table('course_generation_runs')]
#[Fillable([
    'uuid',
    'course_id',
    'user_id',
    'kind',
    'scope',
    'scoped_block_id',
    'writer_provider',
    'reviewer_provider',
    'with_review',
    'reviewer_not_independent',
    'status',
    'batch_id',
    'estimated_ai_write_calls',
    'estimated_ai_review_calls',
    'estimated_research_calls',
    'ai_call_ceiling',
    'research_call_ceiling',
    'ai_write_calls_consumed',
    'ai_review_calls_consumed',
    'research_calls_consumed',
    'videos_total',
    'videos_completed',
    'videos_failed',
    'current_video_id',
    'stop_reason',
    'started_at',
    'finished_at',
])]
final class CourseGenerationRunEloquentModel extends Model
{
    use GeneratesPublicUuid;
    use LogsActivity;

    /** @var list<string> */
    protected $hidden = ['id', 'course_id', 'user_id', 'scoped_block_id', 'current_video_id', 'batch_id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'with_review' => false,
        'reviewer_not_independent' => false,
        'ai_write_calls_consumed' => 0,
        'ai_review_calls_consumed' => 0,
        'research_calls_consumed' => 0,
        'videos_completed' => 0,
        'videos_failed' => 0,
    ];

    /**
     * @return BelongsTo<CourseEloquentModel, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(CourseEloquentModel::class, 'course_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CourseBlockEloquentModel, $this>
     */
    public function scopedBlock(): BelongsTo
    {
        return $this->belongsTo(CourseBlockEloquentModel::class, 'scoped_block_id');
    }

    /**
     * @return BelongsTo<CourseVideoEloquentModel, $this>
     */
    public function currentVideo(): BelongsTo
    {
        return $this->belongsTo(CourseVideoEloquentModel::class, 'current_video_id');
    }

    /**
     * @return HasMany<CourseVideoOutcomeEloquentModel, $this>
     */
    public function outcomes(): HasMany
    {
        return $this->hasMany(CourseVideoOutcomeEloquentModel::class, 'course_generation_run_id');
    }

    public function aiCallsConsumed(): int
    {
        return $this->ai_write_calls_consumed + $this->ai_review_calls_consumed;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'scope', 'writer_provider', 'with_review', 'stop_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('course_scripts.run');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'user_id' => 'integer',
            'kind' => GenerationRunKind::class,
            'scope' => GenerationScope::class,
            'scoped_block_id' => 'integer',
            'with_review' => 'boolean',
            'reviewer_not_independent' => 'boolean',
            'status' => GenerationRunStatus::class,
            'estimated_ai_write_calls' => 'integer',
            'estimated_ai_review_calls' => 'integer',
            'estimated_research_calls' => 'integer',
            'ai_call_ceiling' => 'integer',
            'research_call_ceiling' => 'integer',
            'ai_write_calls_consumed' => 'integer',
            'ai_review_calls_consumed' => 'integer',
            'research_calls_consumed' => 'integer',
            'videos_total' => 'integer',
            'videos_completed' => 'integer',
            'videos_failed' => 'integer',
            'current_video_id' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
