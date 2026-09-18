<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
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
 * @mixin \Eloquent
 *
 * @internal
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
