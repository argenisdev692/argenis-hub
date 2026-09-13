<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Domain\Enums\VideoScriptStatus;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;

/**
 * @internal
 *
 * The durable video brief — the input every generation reads (FR-6).
 *
 * @property int $id
 * @property string $uuid
 * @property int $course_id
 * @property int|null $course_block_id
 * @property int $number
 * @property string $title
 * @property string|null $topic
 * @property int|null $declared_duration_minutes
 * @property string|null $objective
 * @property string|null $expected_result
 * @property list<string> $learning_areas
 * @property list<string> $audience_objectives
 * @property list<string> $mandatory_content
 * @property list<string> $errors_to_avoid
 * @property string|null $notes
 * @property bool $needs_review
 * @property int $brief_revision
 * @property VideoScriptStatus $script_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseEloquentModel $course
 * @property-read CourseBlockEloquentModel|null $block
 * @property-read Collection<int, CourseScriptVersionEloquentModel> $scriptVersions
 * @property-read CourseScriptVersionEloquentModel|null $acceptedVersion
 * @property-read Collection<int, CourseSourceDocumentEloquentModel> $assignedDocuments
 *
 * @mixin \Eloquent
 */
#[Table('course_videos')]
#[Fillable([
    'uuid',
    'course_id',
    'course_block_id',
    'number',
    'title',
    'topic',
    'declared_duration_minutes',
    'objective',
    'expected_result',
    'learning_areas',
    'audience_objectives',
    'mandatory_content',
    'errors_to_avoid',
    'notes',
    'needs_review',
    'brief_revision',
    'script_status',
])]
final class CourseVideoEloquentModel extends Model
{
    use GeneratesPublicUuid;

    /** @var list<string> */
    protected $hidden = ['id', 'course_id', 'course_block_id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'brief_revision' => 1,
        'needs_review' => false,
    ];

    /**
     * @return BelongsTo<CourseEloquentModel, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(CourseEloquentModel::class, 'course_id');
    }

    /**
     * @return BelongsTo<CourseBlockEloquentModel, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(CourseBlockEloquentModel::class, 'course_block_id');
    }

    /**
     * @return HasMany<CourseScriptVersionEloquentModel, $this>
     */
    public function scriptVersions(): HasMany
    {
        return $this->hasMany(CourseScriptVersionEloquentModel::class, 'course_video_id');
    }

    /**
     * @return HasOne<CourseScriptVersionEloquentModel, $this>
     */
    public function acceptedVersion(): HasOne
    {
        return $this->hasOne(CourseScriptVersionEloquentModel::class, 'course_video_id')->where('is_accepted', true);
    }

    /**
     * @return HasMany<CourseSourceDocumentEloquentModel, $this>
     */
    public function assignedDocuments(): HasMany
    {
        return $this->hasMany(CourseSourceDocumentEloquentModel::class, 'course_video_id');
    }

    /**
     * @return HasMany<CourseResearchFindingEloquentModel, $this>
     */
    public function researchFindings(): HasMany
    {
        return $this->hasMany(CourseResearchFindingEloquentModel::class, 'course_video_id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeFilterByStatusAndBlock(Builder $query, ?VideoScriptStatus $status, ?int $blockId): Builder
    {
        return $query
            ->when($status, static fn (Builder $query, VideoScriptStatus $status): Builder => $query->where('script_status', $status->value))
            ->when($blockId, static fn (Builder $query, int $blockId): Builder => $query->where('course_block_id', $blockId));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'course_block_id' => 'integer',
            'number' => 'integer',
            'declared_duration_minutes' => 'integer',
            'learning_areas' => 'array',
            'audience_objectives' => 'array',
            'mandatory_content' => 'array',
            'errors_to_avoid' => 'array',
            'needs_review' => 'boolean',
            'brief_revision' => 'integer',
            'script_status' => VideoScriptStatus::class,
        ];
    }
}
