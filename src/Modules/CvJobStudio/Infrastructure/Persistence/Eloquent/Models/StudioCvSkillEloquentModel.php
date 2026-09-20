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
 * `evidence` feeds the context factor κ; ordinals feed the position factor π.
 *
 * @property int $id
 * @property string $uuid
 * @property string $canonical_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @internal
 *
 * @property int $user_id
 * @property int $structure_id
 * @property string|null $raw_name
 * @property string $nature
 * @property string $evidence
 * @property int|null $evidence_bullet_id
 * @property numeric|null $years
 * @property int $first_ordinal
 * @property CarbonImmutable|null $deleted_at
 * @property-read StudioCvStructureEloquentModel|null $structure
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereCanonicalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereEvidence($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereEvidenceBulletId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereFirstOrdinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereNature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereRawName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereStructureId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel whereYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StudioCvSkillEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
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
