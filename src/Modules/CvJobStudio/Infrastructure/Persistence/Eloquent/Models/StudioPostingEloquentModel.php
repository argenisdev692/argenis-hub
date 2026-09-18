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
 * @mixin \Eloquent
 *
 * @internal
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

    /** @return HasMany<StudioScoreEloquentModel, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(StudioScoreEloquentModel::class, 'posting_id');
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
     * ListPostingsHandler and the Excel/PDF exports.
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
            );
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
