<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Database\Factories\ScoutProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versioned matching profile derived from the operator's CV (spec US-1).
 * Only one row per user is current (partial unique).
 *
 * @property int $id
 * @property string $uuid
 * @property-read User $user
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_profiles')]
#[Fillable([
    'uuid',
    'user_id',
    'version',
    'source_cv_uuid',
    'cv_hash',
    'confirmed_skills',
    'potential_skills',
    'proof_points',
    'languages',
    'min_rate_cents',
    'target_countries',
    'weights',
    'is_current',
])]
final class ScoutProfileEloquentModel extends Model
{
    /** @use HasFactory<ScoutProfileFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'confirmed_skills' => 'array',
            'potential_skills' => 'array',
            'proof_points' => 'array',
            'languages' => 'array',
            'min_rate_cents' => 'integer',
            'target_countries' => 'array',
            'weights' => 'array',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ScoutScoreResultEloquentModel, $this>
     */
    public function scoreResults(): HasMany
    {
        return $this->hasMany(ScoutScoreResultEloquentModel::class, 'profile_id');
    }

    protected static function newFactory(): ScoutProfileFactory
    {
        return ScoutProfileFactory::new();
    }
}
