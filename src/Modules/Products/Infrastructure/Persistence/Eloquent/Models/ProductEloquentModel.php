<?php

declare(strict_types=1);

namespace Modules\Products\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceItemEloquentModel;
use Modules\Products\Application\DTOs\ProductFilterData;
use Modules\Products\Domain\Enums\ProductStatus;
use Modules\Products\Domain\Enums\ProductType;
use Shared\Domain\Enums\BillingUnit;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int|null $client_id
 * @property ProductType $type
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $price
 * @property string $currency
 * @property BillingUnit $default_unit
 * @property ProductStatus $status
 * @property string|null $thumbnail
 * @property string $level
 * @property string $language
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $total_hours
 * @property int|null $total_sessions
 * @property string|null $modality
 * @property string|null $notes
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read ClientEloquentModel|null $client
 * @property-read Collection<int, InvoiceEloquentModel> $invoices
 * @property-read Collection<int, InvoiceItemEloquentModel> $invoiceItems
 *
 * @method static Builder<static> published()
 * @method static Builder<static> applyFilters(ProductFilterData $filters)
 * @method static Builder<static>|ProductEloquentModel newModelQuery()
 * @method static Builder<static>|ProductEloquentModel newQuery()
 * @method static Builder<static>|ProductEloquentModel onlyTrashed()
 * @method static Builder<static>|ProductEloquentModel query()
 * @method static Builder<static>|ProductEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ProductEloquentModel withoutTrashed()
 * @method static ProductFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Table('products')]
#[Fillable([
    'uuid',
    'user_id',
    'client_id',
    'type',
    'title',
    'slug',
    'description',
    'price',
    'currency',
    'default_unit',
    'status',
    'thumbnail',
    'level',
    'language',
    'start_date',
    'end_date',
    'total_hours',
    'total_sessions',
    'modality',
    'notes',
])]
#[Hidden(['id'])]
final class ProductEloquentModel extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    protected static function booted(): void
    {
        self::creating(function (self $product): void {
            if (empty($product->uuid)) {
                $product->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ClientEloquentModel, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientEloquentModel::class, 'client_id');
    }

    /**
     * Invoices whose header points at this product (a single-course invoice).
     *
     * Inverse of `InvoiceEloquentModel::product()` — declared because
     * `invoices.product_id` is a foreign key and every FK in this project
     * carries both sides of the relation.
     *
     * @return HasMany<InvoiceEloquentModel, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(InvoiceEloquentModel::class, 'product_id');
    }

    /**
     * Every invoice line billed against this product — the authoritative view
     * of what the catalog entry has earned, since a mixed invoice bills a
     * course alongside unrelated work.
     *
     * Inverse of `InvoiceItemEloquentModel::product()` — declared because
     * `invoice_items.product_id` is a foreign key and every FK in this project
     * carries both sides of the relation.
     *
     * @return HasMany<InvoiceItemEloquentModel, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItemEloquentModel::class, 'product_id');
    }

    /**
     * Rows the invoice line-item picker is allowed to offer.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNull('deleted_at')->where('status', ProductStatus::Published);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApplyFilters(Builder $query, ProductFilterData $f): Builder
    {
        return $query
            ->when($f->search !== null, fn (Builder $q) => $q->where(function (Builder $w) use ($f): void {
                $term = "%{$f->search}%";
                $w->where('title', 'like', $term)->orWhere('slug', 'like', $term);
            }))
            ->when($f->type !== null, fn (Builder $q) => $q->where('type', $f->type))
            ->when($f->productStatus !== null, fn (Builder $q) => $q->where('status', $f->productStatus))
            ->when($f->status === 'active', fn (Builder $q) => $q->whereNull('deleted_at'))
            ->when($f->status === 'deleted', fn (Builder $q) => $q->onlyTrashed())
            ->when($f->status === null || $f->status === '', fn (Builder $q) => $q->withTrashed())
            ->when($f->dateFrom !== null && $f->dateTo !== null, fn (Builder $q) => $q->whereBetween('created_at', [
                CarbonImmutable::parse($f->dateFrom)->startOfDay(),
                CarbonImmutable::parse($f->dateTo)->endOfDay(),
            ]))
            ->when($f->dateFrom !== null && $f->dateTo === null, fn (Builder $q) => $q->where(
                'created_at', '>=', CarbonImmutable::parse($f->dateFrom)->startOfDay(),
            ))
            ->when($f->dateFrom === null && $f->dateTo !== null, fn (Builder $q) => $q->where(
                'created_at', '<=', CarbonImmutable::parse($f->dateTo)->endOfDay(),
            ))
            ->orderBy($f->sortField, $f->sortOrder === 1 ? 'asc' : 'desc');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'client_id' => 'integer',
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'default_unit' => BillingUnit::class,
            'price' => 'decimal:2',
            'total_hours' => 'decimal:2',
            'total_sessions' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'type', 'title', 'slug', 'description', 'price', 'currency',
                'default_unit', 'status', 'level', 'language', 'start_date',
                'end_date', 'total_hours', 'total_sessions', 'modality',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('products.product');
    }
}
