<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Domain\Enums\SourceDocumentKind;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;

/**
 * @internal An uploaded file attached to a course: the index, a content file with the
 * author's notes, or a style reference (FR-1b, FR-8, FR-10).
 *
 * @property int $id
 * @property string $uuid
 * @property int $course_id
 * @property int|null $course_video_id
 * @property SourceDocumentKind $kind
 * @property string $original_name
 * @property string $path
 * @property string $mime
 * @property int $size_bytes
 * @property string $checksum
 * @property string|null $extracted_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseEloquentModel $course
 * @property-read CourseVideoEloquentModel|null $video
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereCourseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereCourseVideoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereExtractedText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereOriginalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseSourceDocumentEloquentModel whereUuid($value)
 *
 * @mixin \Eloquent
 */
#[Table('course_source_documents')]
#[Fillable(['uuid', 'course_id', 'course_video_id', 'kind', 'original_name', 'path', 'mime', 'size_bytes', 'checksum', 'extracted_text'])]
final class CourseSourceDocumentEloquentModel extends Model
{
    use GeneratesPublicUuid;

    /** @var list<string> */
    protected $hidden = ['id', 'course_id', 'course_video_id', 'path', 'checksum', 'extracted_text'];

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
            'kind' => SourceDocumentKind::class,
            'size_bytes' => 'integer',
        ];
    }
}
