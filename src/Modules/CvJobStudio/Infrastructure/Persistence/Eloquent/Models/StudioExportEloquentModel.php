<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * `text_extraction_verified` is the persisted SC-5 assertion: the generated
 * file's own text is re-extracted after generation and compared to intent.
 *
 * @property int $id
 * @property string $uuid
 * @property string $format
 * @property string $path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property int|null $cv_version_id
 * @property string|null $language
 * @property string $disk
 * @property int $bytes
 * @property bool $text_extraction_verified
 * @property int $extracted_char_count
 * @property array<array-key, mixed>|null $structural_checks
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioCvVersionEloquentModel|null $cvVersion
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereCvVersionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereDisk($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereExtractedCharCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereFormat($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereLanguage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereStructuralChecks($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereTextExtractionVerified($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioExportEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_exports')]
#[Fillable([
    'uuid', 'user_id', 'cv_version_id', 'format', 'language', 'disk', 'path',
    'bytes', 'text_extraction_verified', 'extracted_char_count', 'structural_checks',
])]
final class StudioExportEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioExportEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioCvVersionEloquentModel, $this> */
    public function cvVersion(): BelongsTo
    {
        return $this->belongsTo(StudioCvVersionEloquentModel::class, 'cv_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cv_version_id' => 'integer',
            'bytes' => 'integer',
            'text_extraction_verified' => 'boolean',
            'extracted_char_count' => 'integer',
            'structural_checks' => 'array',
        ];
    }
}
