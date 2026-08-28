<?php

declare(strict_types=1);

namespace Modules\Portfolios\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PortfolioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Portfolios\Application\DTOs\PortfolioFilterData;
use Modules\Portfolios\Infrastructure\Cache\PortfolioPublicFeedCache;
use Shared\Domain\Ports\StoragePort;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $title
 * @property string $client_name
 * @property string $project_type
 * @property list<string>|null $tech_stack
 * @property string|null $live_url
 * @property CarbonImmutable|null $published_at
 * @property bool $is_public
 * @property string|null $cover_path
 * @property string|null $video_path
 * @property string|null $description
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $cover_url
 * @property-read string|null $video_url
 * @property-read User $user
 * @property-read Collection<int, PortfolioMediaEloquentModel> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> applyFilters(PortfolioFilterData $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static> published()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PortfolioEloquentModel withoutTrashed()
 * @method static PortfolioFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'uuid',
    'user_id',
    'title',
    'client_name',
    'project_type',
    'tech_stack',
    'live_url',
    'published_at',
    'is_public',
    'cover_path',
    'video_path',
    'description',
    'sort_order',
])]
#[Hidden(['id'])]
final class PortfolioEloquentModel extends Model
{
    /** @use HasFactory<PortfolioFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'portfolios';

    /** @var list<string> */
    private const array SORTABLE = ['created_at', 'updated_at', 'title', 'client_name', 'published_at', 'sort_order'];

    protected static function newFactory(): PortfolioFactory
    {
        return PortfolioFactory::new();
    }

    protected static function booted(): void
    {
        self::creating(function (self $portfolio): void {
            if (empty($portfolio->uuid)) {
                $portfolio->uuid = (string) Str::uuid7();
            }
        });

        // Keep the public landing-page feed in step with admin edits.
        self::saved(static function (): void {
            PortfolioPublicFeedCache::flush();
        });
        self::deleted(static function (): void {
            PortfolioPublicFeedCache::flush();
        });
        self::restored(static function (): void {
            PortfolioPublicFeedCache::flush();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ordered gallery images. The child rows carry no independent lifecycle —
     * they are replaced wholesale when the aggregate is written.
     *
     * @return HasMany<PortfolioMediaEloquentModel, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(PortfolioMediaEloquentModel::class, 'portfolio_id')->orderBy('sort_order');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tech_stack' => 'array',
            'published_at' => 'immutable_datetime',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Absolute public URL for the cover image, resolved through the Shared
     * storage port. The stored value is an R2 object key (the normalization
     * migration strips any legacy absolute host).
     */
    protected function coverUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->cover_path === null || $this->cover_path === ''
            ? null
            : app(StoragePort::class)->publicUrl($this->cover_path));
    }

    /**
     * Absolute public URL for the showcase video, same resolution as
     * {@see self::coverUrl()}.
     */
    protected function videoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->video_path === null || $this->video_path === ''
            ? null
            : app(StoragePort::class)->publicUrl($this->video_path));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'title',
                'client_name',
                'project_type',
                'tech_stack',
                'live_url',
                'published_at',
                'is_public',
                'cover_path',
                'video_path',
                'description',
                'sort_order',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('portfolios.portfolio');
    }

    /**
     * The public landing-page feed: visible, non-deleted, already published.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNull('deleted_at')
            ->where('is_public', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', CarbonImmutable::now());
    }

    /**
     * The single source of truth for the admin list query AND the export query
     * (BACKEND-PHP §5.2) — `search` over the identifying columns, `status` as the
     * soft-delete axis (`active` / `deleted` / all), an inclusive `created_at`
     * window, and a whitelisted sort.
     */
    public function scopeApplyFilters(Builder $query, PortfolioFilterData $f): Builder
    {
        $sortField = in_array($f->sortField, self::SORTABLE, true) ? $f->sortField : 'created_at';

        return $query
            ->when($f->search, fn (Builder $q, string $s) => $q->where(function (Builder $q) use ($s): void {
                $q->where('title', 'like', "%{$s}%")
                    ->orWhere('client_name', 'like', "%{$s}%")
                    ->orWhere('project_type', 'like', "%{$s}%");
            }))
            ->when($f->status === 'active', fn (Builder $q) => $q->whereNull('deleted_at'))
            ->when($f->status === 'deleted', fn (Builder $q) => $q->onlyTrashed())
            ->when($f->status === '' || $f->status === null, fn (Builder $q) => $q->withTrashed())
            ->when($f->dateFrom && $f->dateTo, fn (Builder $q) => $q->whereBetween('created_at', [
                CarbonImmutable::parse($f->dateFrom)->startOfDay(),
                CarbonImmutable::parse($f->dateTo)->endOfDay(),
            ]))
            ->when($f->dateFrom && ! $f->dateTo, fn (Builder $q) => $q->where(
                'created_at', '>=', CarbonImmutable::parse($f->dateFrom)->startOfDay(),
            ))
            ->when(! $f->dateFrom && $f->dateTo, fn (Builder $q) => $q->where(
                'created_at', '<=', CarbonImmutable::parse($f->dateTo)->endOfDay(),
            ))
            ->orderBy($sortField, $f->sortOrder === 1 ? 'asc' : 'desc');
    }
}
