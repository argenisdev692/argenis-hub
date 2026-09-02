<?php

declare(strict_types=1);

namespace Modules\Services\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;
use Modules\Services\Application\DTOs\ServiceFilterData;
use Modules\Services\Infrastructure\Cache\ServicePublicFeedCache;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Collection<int, InvoiceItemEloquentModel> $invoiceItems
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> active()
 * @method static \Illuminate\Database\Eloquent\Builder<static> applyFilters(ServiceFilterData $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ServiceEloquentModel withoutTrashed()
 * @method static ServiceFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Fillable(['uuid', 'name', 'slug', 'description', 'is_active', 'sort_order', 'user_id'])]
#[Hidden(['id'])]
final class ServiceEloquentModel extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'services';

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    protected static function booted(): void
    {
        self::creating(function (self $service): void {
            if (empty($service->uuid)) {
                $service->uuid = (string) Str::uuid7();
            }
        });

        // Keep the landing-page `<select>` feed in step with admin edits.
        self::saved(static function (): void {
            ServicePublicFeedCache::flush();
        });
        self::deleted(static function (): void {
            ServicePublicFeedCache::flush();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The invoice lines billed against this catalog service.
     *
     * Inverse of `InvoiceItemEloquentModel::service()` — declared because
     * `invoice_items.service_id` is a foreign key and every FK in this project
     * carries both sides of the relation. The FK is `nullOnDelete`, so deleting
     * a service leaves the already-issued invoice lines intact as free text.
     *
     * @return HasMany<InvoiceItemEloquentModel, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItemEloquentModel::class, 'service_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'description', 'is_active', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('services.service');
    }

    /**
     * Active, non-deleted rows in display order — the shape the public feed
     * and the landing-page `<select>` both need.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at')->where('is_active', true);
    }

    public function scopeApplyFilters(Builder $query, ServiceFilterData $f): Builder
    {
        return $query
            ->when($f->search, fn (Builder $q, string $s) => $q->where(function (Builder $q) use ($s): void {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%");
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
            ->orderBy($f->sortField, $f->sortOrder === 1 ? 'asc' : 'desc');
    }
}
