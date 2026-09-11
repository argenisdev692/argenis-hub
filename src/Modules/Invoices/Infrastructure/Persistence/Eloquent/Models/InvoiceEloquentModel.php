<?php

declare(strict_types=1);

namespace Modules\Invoices\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
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
use Modules\Clients\Infrastructure\Persistence\Eloquent\Models\ClientEloquentModel;
use Modules\Invoices\Application\DTOs\InvoiceFilterData;
use Modules\Invoices\Domain\Enums\TaxMode;
use Modules\Invoices\Domain\Ports\InvoiceRepositoryPort;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models\PaymentAccountEloquentModel;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @internal Application must depend on {@see InvoiceRepositoryPort}, not this model.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $client_id
 * @property int|null $product_id
 * @property string $invoice_number
 * @property int $sequence
 * @property int $year
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property string $currency
 * @property TaxMode $tax_mode
 * @property string|null $tax_rate
 * @property string $tax_label
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total
 * @property bool $is_paid
 * @property PaymentMethod|null $payment_method
 * @property int|null $payment_account_id
 * @property array<string, mixed>|null $payment_details_json
 * @property string|null $transfer_number
 * @property Carbon|null $payment_date
 * @property string|null $amount_received
 * @property string|null $notes
 * @property string|null $additional_notes
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read ClientEloquentModel $client
 * @property-read ProductEloquentModel|null $product
 * @property-read PaymentAccountEloquentModel|null $paymentAccount
 * @property-read Collection<int, InvoiceItemEloquentModel> $items
 *
 * @mixin \Eloquent
 */
#[Table('invoices')]
#[Fillable([
    'uuid',
    'user_id',
    'client_id',
    'product_id',
    'invoice_number',
    'sequence',
    'year',
    'issue_date',
    'due_date',
    'currency',
    'tax_mode',
    'tax_rate',
    'tax_label',
    'subtotal',
    'tax_amount',
    'total',
    'is_paid',
    'payment_method',
    'payment_account_id',
    'payment_details_json',
    'transfer_number',
    'payment_date',
    'amount_received',
    'notes',
    'additional_notes',
])]
final class InvoiceEloquentModel extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (InvoiceEloquentModel $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ClientEloquentModel, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ClientEloquentModel::class, 'client_id');
    }

    /**
     * @return BelongsTo<ProductEloquentModel, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            ProductEloquentModel::class,
            'product_id',
        );
    }

    /**
     * Kept for reporting ("which invoices settled through Remitly"). The PDF
     * renders `payment_details_json` instead, so editing the account never
     * rewrites an already-issued document.
     *
     * @return BelongsTo<PaymentAccountEloquentModel, $this>
     */
    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccountEloquentModel::class, 'payment_account_id');
    }

    /**
     * @return HasMany<InvoiceItemEloquentModel, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItemEloquentModel::class, 'invoice_id')->orderBy('sort_order');
    }

    /**
     * The single source of truth for list AND export filtering (BACKEND-PHP
     * §5.2) — including the soft-delete `status` branch, so the list endpoint
     * and the exporter cannot drift apart on what "suspended" means.
     *
     * @param  Builder<InvoiceEloquentModel>  $query
     * @return Builder<InvoiceEloquentModel>
     */
    public function scopeApplyFilters(Builder $query, InvoiceFilterData $filters): Builder
    {
        return $query
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            // The only branch that lifts the SoftDeletes scope. Without it
            // "All" would return exactly what "Active" returns, and the
            // suspended badge the admin table renders would be unreachable.
            ->when($filters->status === 'all', fn ($q) => $q->withTrashed())
            // Orthogonal to the branches above — the settlement axis. Compared
            // against the literal rather than cast to bool so an unrecognised
            // value filters nothing instead of silently meaning "unpaid".
            ->when(
                $filters->paymentStatus !== null,
                fn ($q) => $q->where('is_paid', $filters->paymentStatus === 'paid'),
            )
            ->when($filters->search !== null, fn ($q) => $q->where(function ($w) use ($filters): void {
                $term = "%{$filters->search}%";
                $w->where('invoice_number', 'like', $term)
                    ->orWhereHas('client', fn ($c) => $c->where('client_name', 'like', $term));
            }))
            ->when(
                $filters->year !== null,
                fn ($q) => $q->where('year', $filters->year),
            )
            ->when(
                $filters->clientUuid !== null,
                fn ($q) => $q->whereHas('client', fn ($c) => $c->where('uuid', $filters->clientUuid)),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo !== null,
                fn ($q) => $q->whereBetween('issue_date', [
                    CarbonImmutable::parse($filters->dateFrom)->startOfDay(),
                    CarbonImmutable::parse($filters->dateTo)->endOfDay(),
                ]),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo === null,
                fn ($q) => $q->where('issue_date', '>=', CarbonImmutable::parse($filters->dateFrom)->startOfDay()),
            )
            ->when(
                $filters->dateTo !== null && $filters->dateFrom === null,
                fn ($q) => $q->where('issue_date', '<=', CarbonImmutable::parse($filters->dateTo)->endOfDay()),
            );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'client_id' => 'integer',
            'product_id' => 'integer',
            'sequence' => 'integer',
            'year' => 'integer',
            'issue_date' => 'date',
            'due_date' => 'date',
            'tax_mode' => TaxMode::class,
            'tax_rate' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'is_paid' => 'boolean',
            'payment_method' => PaymentMethod::class,
            'payment_account_id' => 'integer',
            'payment_details_json' => 'array',
            'payment_date' => 'date',
            'amount_received' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'invoice_number',
                'client_id',
                'product_id',
                'issue_date',
                'due_date',
                'currency',
                'tax_mode',
                'tax_rate',
                'subtotal',
                'tax_amount',
                'total',
                'is_paid',
                'payment_method',
                'payment_account_id',
                'transfer_number',
                'payment_date',
                'amount_received',
                'notes',
                'additional_notes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('invoices');
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }
}
