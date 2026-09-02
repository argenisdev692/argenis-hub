<?php

declare(strict_types=1);

namespace Modules\PaymentAccounts\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentAccountFactory;
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
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\PaymentAccounts\Application\DTOs\PaymentAccountFilterData;
use Modules\PaymentAccounts\Domain\Enums\PaymentMethod;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property PaymentMethod $method
 * @property string|null $currency
 * @property string $label
 * @property string|null $beneficiary
 * @property string|null $bank_name
 * @property string|null $iban
 * @property string|null $bic
 * @property string|null $account_number
 * @property string|null $routing_number
 * @property string|null $holder_email
 * @property string|null $holder_phone
 * @property string|null $instructions
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, InvoiceEloquentModel> $invoices
 *
 * @method static Builder<static> usableForCurrency(string $currency)
 * @method static Builder<static> applyFilters(PaymentAccountFilterData $filters)
 * @method static Builder<static>|PaymentAccountEloquentModel newModelQuery()
 * @method static Builder<static>|PaymentAccountEloquentModel newQuery()
 * @method static Builder<static>|PaymentAccountEloquentModel onlyTrashed()
 * @method static Builder<static>|PaymentAccountEloquentModel query()
 * @method static Builder<static>|PaymentAccountEloquentModel withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|PaymentAccountEloquentModel withoutTrashed()
 * @method static PaymentAccountFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Table('payment_accounts')]
#[Fillable([
    'uuid',
    'user_id',
    'method',
    'currency',
    'label',
    'beneficiary',
    'bank_name',
    'iban',
    'bic',
    'account_number',
    'routing_number',
    'holder_email',
    'holder_phone',
    'instructions',
    'is_default',
    'is_active',
    'sort_order',
])]
#[Hidden(['id'])]
final class PaymentAccountEloquentModel extends Model
{
    /** @use HasFactory<PaymentAccountFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected static function newFactory(): PaymentAccountFactory
    {
        return PaymentAccountFactory::new();
    }

    protected static function booted(): void
    {
        self::creating(function (self $account): void {
            if (empty($account->uuid)) {
                $account->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Invoices that referenced this account — the "which invoices settled
     * through Remitly" question.
     *
     * Inverse of `InvoiceEloquentModel::paymentAccount()` — declared because
     * `invoices.payment_account_id` is a foreign key and every FK in this
     * project carries both sides of the relation. The FK is `nullOnDelete`;
     * the invoice keeps rendering from its own `payment_details_json` snapshot
     * even after the account is gone.
     *
     * @return HasMany<InvoiceEloquentModel, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(InvoiceEloquentModel::class, 'payment_account_id');
    }

    /**
     * Accounts that can settle an invoice in `$currency`: either pinned to that
     * currency, or currency-agnostic (`currency = null`). Default-first, so
     * `->first()` is the account the invoice form pre-selects.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUsableForCurrency(Builder $query, string $currency): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($currency): void {
                $q->where('currency', strtoupper($currency))->orWhereNull('currency');
            })
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('label');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApplyFilters(Builder $query, PaymentAccountFilterData $f): Builder
    {
        return $query
            ->when($f->search !== null && $f->search !== '', fn (Builder $q) => $q->where(function (Builder $w) use ($f): void {
                $term = "%{$f->search}%";
                $w->where('label', 'like', $term)
                    ->orWhere('beneficiary', 'like', $term)
                    ->orWhere('bank_name', 'like', $term);
            }))
            ->when($f->method !== null, fn (Builder $q) => $q->where('method', $f->method))
            ->when($f->currency !== null && $f->currency !== '', fn (Builder $q) => $q->where('currency', strtoupper((string) $f->currency)))
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
     * Snapshot written to `invoices.payment_details_json`. Copies the account as
     * it stands today so a later edit never rewrites an issued invoice.
     *
     * @return array{
     *     method: string,
     *     currency: string|null,
     *     label: string,
     *     beneficiary: string|null,
     *     bank_name: string|null,
     *     iban: string|null,
     *     bic: string|null,
     *     account_number: string|null,
     *     routing_number: string|null,
     *     holder_email: string|null,
     *     holder_phone: string|null,
     *     instructions: string|null
     * }
     */
    #[\NoDiscard]
    public function toSnapshot(): array
    {
        return [
            'method' => $this->method->value,
            'currency' => $this->currency,
            'label' => $this->label,
            'beneficiary' => $this->beneficiary,
            'bank_name' => $this->bank_name,
            'iban' => $this->iban,
            'bic' => $this->bic,
            'account_number' => $this->account_number,
            'routing_number' => $this->routing_number,
            'holder_email' => $this->holder_email,
            'holder_phone' => $this->holder_phone,
            'instructions' => $this->instructions,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'method' => PaymentMethod::class,
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Account numbers, IBAN and routing numbers are deliberately NOT logged
        // — the activity log is not the place for settlement credentials.
        return LogOptions::defaults()
            ->logOnly(['method', 'currency', 'label', 'bank_name', 'is_default', 'is_active', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('payment_accounts.account');
    }
}
