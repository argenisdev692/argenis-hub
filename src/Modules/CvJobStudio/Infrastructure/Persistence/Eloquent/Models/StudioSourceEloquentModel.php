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
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $name
 * @property string $access_mode
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('studio_sources')]
#[Fillable([
    'uuid', 'user_id', 'kind', 'name', 'endpoint', 'auth_kind', 'tier', 'layer',
    'attribution_required', 'attribution_text', 'redistribution_allowed', 'publish_delay_hours',
    'rate_limit_per_minute', 'status', 'health_checked_at', 'gate_tags', 'resolution_priority',
    'supplies_full_text', 'company_scoped', 'ats_kind', 'endpoint_template', 'supports_since_filter',
    'requires_api_key', 'access_mode', 'resolution_tier', 'usage_restriction',
])]
final class StudioSourceEloquentModel extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioSourceEloquentModel $model): void {
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

    /** @return HasMany<StudioSourceCompanyEloquentModel, $this> */
    public function companies(): HasMany
    {
        return $this->hasMany(StudioSourceCompanyEloquentModel::class, 'source_id');
    }

    /** @return HasMany<StudioSourceLocaleEloquentModel, $this> */
    public function locales(): HasMany
    {
        return $this->hasMany(StudioSourceLocaleEloquentModel::class, 'source_id');
    }

    /**
     * @param  Builder<StudioSourceEloquentModel>  $query
     * @return Builder<StudioSourceEloquentModel>
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
            'tier' => 'integer',
            'attribution_required' => 'boolean',
            'redistribution_allowed' => 'boolean',
            'publish_delay_hours' => 'integer',
            'rate_limit_per_minute' => 'integer',
            'health_checked_at' => 'datetime',
            'gate_tags' => 'array',
            'resolution_priority' => 'integer',
            'supplies_full_text' => 'boolean',
            'company_scoped' => 'boolean',
            'supports_since_filter' => 'boolean',
            'requires_api_key' => 'boolean',
            'resolution_tier' => 'integer',
        ];
    }
}
