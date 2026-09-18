<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The addressable form of a CV (FR-4). `raw_text` on `cvs` stays the immutable
 * source of truth; these rows are derived and re-derivable. Unusable until
 * the candidate confirms the parse (`confirmed_at`, RK-6).
 *
 * @property int $id
 * @property string $uuid
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_cv_structures')]
#[Fillable(['uuid', 'user_id', 'cv_id', 'source_text_hash', 'parser_version', 'parsed_at', 'confirmed_at', 'profile_facts'])]
final class StudioCvStructureEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioCvStructureEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<StudioCvEntryEloquentModel, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(StudioCvEntryEloquentModel::class, 'structure_id');
    }

    /** @return HasMany<StudioCvSkillEloquentModel, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(StudioCvSkillEloquentModel::class, 'structure_id');
    }

    /**
     * @param  Builder<StudioCvStructureEloquentModel>  $query
     * @return Builder<StudioCvStructureEloquentModel>
     */
    public function scopeOwnedBy($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cv_id' => 'integer',
            'parsed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'profile_facts' => 'array',
        ];
    }
}
