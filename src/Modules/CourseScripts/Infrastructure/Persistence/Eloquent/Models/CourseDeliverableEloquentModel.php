<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Domain\Enums\DeliverableDocumentType;
use Modules\CourseScripts\Domain\Enums\DeliverableFormat;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;

/**
 * @internal A rendered file (FR-45…FR-48). `artifact_file_name` is '' for documents
 * that are not practice files.
 *
 * @property int $id
 * @property string $uuid
 * @property int $course_script_version_id
 * @property DeliverableDocumentType $document_type
 * @property string $artifact_file_name
 * @property DeliverableFormat $format
 * @property string $path
 * @property int $size_bytes
 * @property string $checksum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseScriptVersionEloquentModel $scriptVersion
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereArtifactFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereCourseScriptVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereDocumentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CourseDeliverableEloquentModel whereUuid($value)
 *
 * @mixin \Eloquent
 */
#[Table('course_deliverables')]
#[Fillable(['uuid', 'course_script_version_id', 'document_type', 'artifact_file_name', 'format', 'path', 'size_bytes', 'checksum'])]
final class CourseDeliverableEloquentModel extends Model
{
    use GeneratesPublicUuid;

    /** @var list<string> */
    protected $hidden = ['id', 'course_script_version_id', 'path', 'checksum'];

    /** @var array<string, string> */
    protected $attributes = [
        'artifact_file_name' => '',
    ];

    /**
     * @return BelongsTo<CourseScriptVersionEloquentModel, $this>
     */
    public function scriptVersion(): BelongsTo
    {
        return $this->belongsTo(CourseScriptVersionEloquentModel::class, 'course_script_version_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'course_script_version_id' => 'integer',
            'document_type' => DeliverableDocumentType::class,
            'format' => DeliverableFormat::class,
            'size_bytes' => 'integer',
        ];
    }
}
