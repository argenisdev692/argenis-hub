<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\CourseScripts\Domain\Enums\PracticeDecisionOrigin;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Concerns\GeneratesPublicUuid;

/**
 * @internal
 *
 * The practice pack of a script version, modelled on the author's sample
 * `Propuestas_Logistica_Heliantia` (DEC-9).
 *
 * @property int $id
 * @property string $uuid
 * @property int $course_script_version_id
 * @property PracticeDecisionOrigin $decided_by
 * @property string $decision_reason
 * @property string $document_name
 * @property string $header_title
 * @property string $files_summary
 * @property string $setup_instruction
 * @property list<array{demo_label: string, section_number: string, purpose: string}> $usage
 * @property string $instructor_note
 * @property list<array<string, mixed>> $designed_contrasts
 * @property list<array<string, mixed>> $artifacts
 * @property array<string, int>|null $review_scores
 * @property list<array{target: string, text: string}>|null $review_objections
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CourseScriptVersionEloquentModel $scriptVersion
 *
 * @mixin \Eloquent
 */
#[Table('course_practice_versions')]
#[Fillable([
    'uuid',
    'course_script_version_id',
    'decided_by',
    'decision_reason',
    'document_name',
    'header_title',
    'files_summary',
    'setup_instruction',
    'usage',
    'instructor_note',
    'designed_contrasts',
    'artifacts',
    'review_scores',
    'review_objections',
])]
final class CoursePracticeVersionEloquentModel extends Model
{
    use GeneratesPublicUuid;

    /** @var list<string> */
    protected $hidden = ['id', 'course_script_version_id'];

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
            'decided_by' => PracticeDecisionOrigin::class,
            'usage' => 'array',
            'designed_contrasts' => 'array',
            'artifacts' => 'array',
            'review_scores' => 'array',
            'review_objections' => 'array',
        ];
    }
}
