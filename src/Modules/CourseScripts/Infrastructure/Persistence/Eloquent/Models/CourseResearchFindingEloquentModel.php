<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;

/**
 * @internal
 *
 * A source found while researching the subject (video id null) or one video.
 *
 * @property int $id
 * @property string $uuid
 * @property int $course_id
 * @property int|null $course_video_id
 * @property string $provider
 * @property string $query
 * @property string $url
 * @property string $title
 * @property string $content
 * @property float|null $score
 * @property bool $full_page_fetched
 * @property Carbon|null $published_at
 * @property Carbon $gathered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseEloquentModel $course
 * @property-read CourseVideoEloquentModel|null $video
 *
 * @mixin \Eloquent
 */
#[Table('course_research_findings')]
#[Fillable(['uuid', 'course_id', 'course_video_id', 'provider', 'query', 'url', 'title', 'content', 'score', 'full_page_fetched', 'published_at', 'gathered_at'])]
final class CourseResearchFindingEloquentModel extends Model
{
    use GeneratesPublicUuid;

    /** @var list<string> */
    protected $hidden = ['id', 'course_id', 'course_video_id'];

    /**
     * @return BelongsTo<CourseEloquentModel, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(CourseEloquentModel::class, 'course_id');
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
            'course_id' => 'integer',
            'course_video_id' => 'integer',
            'score' => 'float',
            'full_page_fetched' => 'boolean',
            'published_at' => 'datetime',
            'gathered_at' => 'datetime',
        ];
    }
}
