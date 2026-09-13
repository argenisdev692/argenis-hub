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
