<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;

/**
 * @internal
 *
 * @property int $id
 * @property string $uuid
 * @property int $course_id
 * @property int $number
 * @property string $title
 * @property int|null $declared_duration_minutes
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseEloquentModel $course
 * @property-read Collection<int, CourseVideoEloquentModel> $videos
 * @property-read Collection<int, CourseGenerationRunEloquentModel> $scopedRuns
 * @property-read int|null $videos_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel dRuns()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel whereDeclaredDurationMinutes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseBlockEloquentModel whereUuid($value)
 *
 * @mixin \Eloquent
 */
#[Table('course_blocks')]
#[Fillable(['uuid', 'course_id', 'number', 'title', 'declared_duration_minutes', 'position'])]
final class CourseBlockEloquentModel extends Model
{
    use GeneratesPublicUuid;

    /** @var list<string> */
    protected $hidden = ['id', 'course_id'];

    /**
     * @return BelongsTo<CourseEloquentModel, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(CourseEloquentModel::class, 'course_id');
    }

    /**
     * @return HasMany<CourseVideoEloquentModel, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(CourseVideoEloquentModel::class, 'course_block_id');
    }

    /**
     * Runs scoped to this block (inverse of `CourseGenerationRunEloquentModel::scopedBlock()`).
     *
     * @return HasMany<CourseGenerationRunEloquentModel, $this>
     */
    public function scopedRuns(): HasMany
    {
        return $this->hasMany(CourseGenerationRunEloquentModel::class, 'scoped_block_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'number' => 'integer',
            'declared_duration_minutes' => 'integer',
            'position' => 'integer',
        ];
    }
}
