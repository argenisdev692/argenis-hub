<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\StudioPostingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $profile_id
 * @property string $title
 * @property string|null $remote_scope
 * @property string $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read StudioProfileEloquentModel $profile
 * @property-read Collection<int, StudioScoreEloquentModel> $scores
 *
 * @internal
 *
 * @property string $canonical_url
 * @property string $url_hash
 * @property string|null $fingerprint
 * @property string|null $source
 * @property string|null $employer_name
 * @property string|null $location_text
 * @property CarbonImmutable|null $posted_at
 * @property string|null $posted_at_source
 * @property string|null $salary_text
 * @property string|null $currency
 * @property string|null $attribution
 * @property string|null $discovery_channel
 * @property string|null $apply_destination
 * @property string|null $ats_kind
 * @property int|null $preferred_source_id
 * @property bool $is_expired
 * @property CarbonImmutable|null $expired_at
 * @property CarbonImmutable|null $last_alive_check_at
 * @property string|null $alive_check_method
 * @property CarbonImmutable|null $first_seen_at
 * @property CarbonImmutable|null $last_seen_at
 * @property numeric|null $d_disc
 * @property array<array-key, mixed>|null $d_disc_components
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, StudioApplicationEloquentModel> $applications
 * @property-read int|null $applications_count
 * @property-read Collection<int, StudioGateResultEloquentModel> $gateResults
 * @property-read int|null $gate_results_count
 * @property-read Collection<int, StudioPostingSourceEloquentModel> $postingSources
 * @property-read int|null $posting_sources_count
 * @property-read Collection<int, StudioRequirementEloquentModel> $requirements
 * @property-read int|null $requirements_count
 * @property-read int|null $scores_count
 * @property-read Collection<int, StudioPostingSightingEloquentModel> $sightings
 * @property-read int|null $sightings_count
 * @property-read Collection<int, StudioPostingTextEloquentModel> $texts
 * @property-read int|null $texts_count
 *
 * @method static Builder<static>|StudioPostingEloquentModel applyFilters(\Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData $filters)
 * @method static Builder<static>|StudioPostingEloquentModel applySort(\Modules\CvJobStudio\Application\DTOs\StudioPostingFilterData $filters)
 * @method static \Database\Factories\StudioPostingFactory factory($count = null, $state = [])
 * @method static Builder<static>|StudioPostingEloquentModel newModelQuery()
 * @method static Builder<static>|StudioPostingEloquentModel newQuery()
 * @method static Builder<static>|StudioPostingEloquentModel onlyTrashed()
 * @method static Builder<static>|StudioPostingEloquentModel ownedBy(int $userId)
 * @method static Builder<static>|StudioPostingEloquentModel query()
 * @method static Builder<static>|StudioPostingEloquentModel whereAliveCheckMethod($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereApplyDestination($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereAtsKind($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereAttribution($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereCanonicalUrl($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereCurrency($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereDDisc($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereDDiscComponents($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereDiscoveryChannel($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereEmployerName($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereExpiredAt($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereFingerprint($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereFirstSeenAt($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereId($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereIsExpired($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereLastAliveCheckAt($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereLastSeenAt($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereLocationText($value)
 * @method static Builder<static>|StudioPostingEloquentModel wherePostedAt($value)
 * @method static Builder<static>|StudioPostingEloquentModel wherePostedAtSource($value)
 * @method static Builder<static>|StudioPostingEloquentModel wherePreferredSourceId($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereProfileId($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereRemoteScope($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereSalaryText($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereSource($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereStatus($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereTitle($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereUrlHash($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereUserId($value)
 * @method static Builder<static>|StudioPostingEloquentModel whereUuid($value)
 * @method static Builder<static>|StudioPostingEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|StudioPostingEloquentModel withoutTrashed()
 *
 * @mixin \Eloquent
 */
#[Table('studio_postings')]
#[Fillable([
    'uuid',
    'user_id',
    'profile_id',
    'canonical_url',
    'url_hash',
    'fingerprint',
    'source',
    'employer_name',
    'title',
    'location_text',
    'remote_scope',
    'posted_at',
    'posted_at_source',
    'salary_text',
    'currency',
    'attribution',
    'status',
    'discovery_channel',
    'apply_destination',
    'ats_kind',
    'preferred_source_id',
    'is_expired',
    'expired_at',
    'last_alive_check_at',
    'alive_check_method',
    'first_seen_at',
    'last_seen_at',
    'd_disc',
    'd_disc_components',
])]
final class StudioPostingEloquentModel extends Model
{
    /** @use HasFactory<StudioPostingFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /** @var list<string> */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (StudioPostingEloquentModel $model): void {
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

    /** @return BelongsTo<StudioProfileEloquentModel, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(StudioProfileEloquentModel::class, 'profile_id');
    }

    /** @return HasMany<StudioPostingTextEloquentModel, $this> */
    public function texts(): HasMany
    {
        return $this->hasMany(StudioPostingTextEloquentModel::class, 'posting_id');
    }

    /** @return HasMany<StudioRequirementEloquentModel, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(StudioRequirementEloquentModel::class, 'posting_id');
    }

    /** @return HasMany<StudioGateResultEloquentModel, $this> */
    public function gateResults(): HasMany
    {
        return $this->hasMany(StudioGateResultEloquentModel::class, 'posting_id');
    }

    /**
     * Newest first — the same order as `EloquentStudioScoreRepository::latestForPosting()`.
     * Every consumer (list DTO, Show, export) reads `scores->first()` as "the
     * latest score", so the order lives here once instead of at each call site.
     *
     * @return HasMany<StudioScoreEloquentModel, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(StudioScoreEloquentModel::class, 'posting_id')
            ->orderByDesc('computed_at')
            ->orderByDesc('id');
    }

    /** @return HasMany<StudioPostingSourceEloquentModel, $this> */
    public function postingSources(): HasMany
    {
        return $this->hasMany(StudioPostingSourceEloquentModel::class, 'posting_id');
    }

    /** @return HasMany<StudioPostingSightingEloquentModel, $this> */
    public function sightings(): HasMany
    {
        return $this->hasMany(StudioPostingSightingEloquentModel::class, 'posting_id');
    }

    /** @return HasMany<StudioApplicationEloquentModel, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(StudioApplicationEloquentModel::class, 'posting_id');
    }

    /**
     * Shared list/export filter (BACKEND-PHP §5.2) — single source for
     * ListPostingsHandler and the Excel/PDF exports. `scoredOnly` keeps
     * unscored postings (queued, mid-pipeline, or link-only `reference` rows)
     * out of the default list; they have no fit to rank and cannot be tailored.
     *
     * @param  Builder<StudioPostingEloquentModel>  $query
     * @return Builder<StudioPostingEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, StudioPostingFilterData $filters): Builder
    {
        return $query
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->when($filters->status === 'all', fn ($q) => $q->withTrashed())
            ->when($filters->search !== null, fn ($q) => $q->where(function ($w) use ($filters): void {
                $term = "%{$filters->search}%";
                $w->where('title', 'like', $term)
                    ->orWhere('employer_name', 'like', $term)
                    ->orWhere('location_text', 'like', $term);
            }))
            ->when(
                $filters->remoteScope !== null,
                fn ($q) => $q->where('remote_scope', $filters->remoteScope),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo !== null,
                fn ($q) => $q->whereBetween('created_at', [
                    CarbonImmutable::parse($filters->dateFrom)->startOfDay(),
                    CarbonImmutable::parse($filters->dateTo)->endOfDay(),
                ]),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo === null,
                fn ($q) => $q->where('created_at', '>=', CarbonImmutable::parse($filters->dateFrom)->startOfDay()),
            )
            ->when(
                $filters->dateTo !== null && $filters->dateFrom === null,
                fn ($q) => $q->where('created_at', '<=', CarbonImmutable::parse($filters->dateTo)->endOfDay()),
            )
            ->when($filters->stages !== null && $filters->stages !== [], fn ($q) => $q->whereIn('status', $filters->stages))
            ->when($filters->scoredOnly, fn ($q) => $q->whereHas('scores'))
            ->applySort($filters);
    }

    /**
     * Whitelisted sort with a stable `id` tiebreak so paging never repeats or
     * skips a row. `fit` orders by the latest score through a correlated
     * subquery; unscored postings always sink to the bottom (`nulls last`)
     * whichever direction is chosen, because "no score" is not "score 0".
     *
     * @param  Builder<StudioPostingEloquentModel>  $query
     * @return Builder<StudioPostingEloquentModel>
     */
    public function scopeApplySort(Builder $query, StudioPostingFilterData $filters): Builder
    {
        $direction = $filters->sortOrder === 1 ? 'asc' : 'desc';
        $field = in_array($filters->sortField, StudioPostingFilterData::SORTABLE, true) ? $filters->sortField : 'created_at';

        if ($field === 'fit') {
            $latestTotal = StudioScoreEloquentModel::query()
                ->select('total_score')
                ->whereColumn('posting_id', $query->qualifyColumn('id'))
                ->orderByDesc('computed_at')
                ->orderByDesc('id')
                ->limit(1);

            $query->orderByRaw("({$latestTotal->toSql()}) {$direction} nulls last", $latestTotal->getBindings());
        } else {
            $query->orderBy($field, $direction);
        }

        return $query->orderBy('id', $direction);
    }

    /**
     * @param  Builder<StudioPostingEloquentModel>  $query
     * @return Builder<StudioPostingEloquentModel>
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
            'profile_id' => 'integer',
            'preferred_source_id' => 'integer',
            'posted_at' => 'datetime',
            'expired_at' => 'datetime',
            'last_alive_check_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_expired' => 'boolean',
            'd_disc' => 'decimal:4',
            'd_disc_components' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'employer_name', 'status', 'remote_scope', 'is_expired'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('studio.postings');
    }

    protected static function newFactory(): StudioPostingFactory
    {
        return StudioPostingFactory::new();
    }
}
