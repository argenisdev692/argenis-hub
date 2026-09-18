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
 * `evidence` feeds the context factor κ; ordinals feed the position factor π.
 *
 * @property int $id
 * @property string $uuid
 * @property string $canonical_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_cv_skills')]
#[Fillable(['uuid', 'user_id', 'structure_id', 'canonical_name', 'raw_name', 'nature', 'evidence', 'evidence_bullet_id', 'years', 'first_ordinal'])]
final class StudioCvSkillEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioCvSkillEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<StudioCvStructureEloquentModel, $this> */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(StudioCvStructureEloquentModel::class, 'structure_id');
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
            'structure_id' => 'integer',
            'evidence_bullet_id' => 'integer',
            'years' => 'decimal:1',
            'first_ordinal' => 'integer',
        ];
    }
}
