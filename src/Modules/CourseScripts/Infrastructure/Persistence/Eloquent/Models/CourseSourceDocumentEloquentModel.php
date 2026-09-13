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
 * @internal
 *
 * An uploaded file attached to a course: the index, a content file with the
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
