<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 * @internal
 *
 * @property string $kind
 * @property string|null $endpoint
 * @property string $auth_kind
 * @property int $tier
 * @property string $layer
 * @property bool $attribution_required
 * @property string|null $attribution_text
 * @property bool $redistribution_allowed
 * @property int $publish_delay_hours
 * @property int $rate_limit_per_minute
 * @property string $status
 * @property CarbonImmutable|null $health_checked_at
 * @property array<array-key, mixed>|null $gate_tags
 * @property int $resolution_priority
 * @property bool $supplies_full_text
 * @property bool $company_scoped
 * @property string|null $ats_kind
 * @property string|null $endpoint_template
 * @property bool $supports_since_filter
 * @property bool $requires_api_key
 * @property int $resolution_tier
 * @property string|null $usage_restriction
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, StudioSourceCompanyEloquentModel> $companies
 * @property-read int|null $companies_count
 * @property-read Collection<int, StudioSourceLocaleEloquentModel> $locales
 * @property-read int|null $locales_count
 *
 * @method static Builder<static>|StudioSourceEloquentModel newModelQuery()
 * @method static Builder<static>|StudioSourceEloquentModel newQuery()
 * @method static Builder<static>|StudioSourceEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioSourceEloquentModel ownedBy(int $userId)
 * @method static Builder<static>|StudioSourceEloquentModel query()
 * @method static Builder<static>|StudioSourceEloquentModel whereAccessMode($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereAtsKind($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereAttributionRequired($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereAttributionText($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereAuthKind($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereCompanyScoped($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereEndpoint($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereEndpointTemplate($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereGateTags($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereHealthCheckedAt($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereId($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereKind($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereLayer($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereName($value)
 * @method static Builder<static>|StudioSourceEloquentModel wherePublishDelayHours($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereRateLimitPerMinute($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereRedistributionAllowed($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereRequiresApiKey($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereResolutionPriority($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereResolutionTier($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereStatus($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereSuppliesFullText($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereSupportsSinceFilter($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereTier($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereUsageRestriction($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioSourceEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioSourceEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioSourceEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
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
    public function scopeOwnedBy(Builder $query, int $userId): Builder
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
