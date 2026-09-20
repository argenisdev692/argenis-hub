<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;

/**
 * @internal A source found while researching the subject (video id null) or one video.
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
 * @property-read Collection<int, CourseScriptVersionEloquentModel> $scriptVersions
 * @property-read int|null $script_versions_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereCourseVideoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereFullPageFetched($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereGatheredAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel wherePublishedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereQuery($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseResearchFindingEloquentModel whereUuid($value)
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
     * Script versions that cite this finding (inverse of `CourseScriptVersionEloquentModel::sources()`).
     *
     * @return BelongsToMany<CourseScriptVersionEloquentModel, $this>
     */
    public function scriptVersions(): BelongsToMany
    {
        return $this->belongsToMany(
            CourseScriptVersionEloquentModel::class,
            'course_script_sources',
            'course_research_finding_id',
            'course_script_version_id',
        );
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
