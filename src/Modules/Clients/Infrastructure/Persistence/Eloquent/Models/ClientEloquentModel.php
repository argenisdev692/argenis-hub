<?php

declare(strict_types=1);

namespace Modules\Clients\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ClientFactory;
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
use Modules\Clients\Application\DTOs\ClientFilterData;
use Modules\Clients\Domain\Enums\ClientStatus;
use Modules\Invoices\Infrastructure\Persistence\Eloquent\Models\InvoiceEloquentModel;
use Modules\Products\Infrastructure\Persistence\Eloquent\Models\ProductEloquentModel;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $client_name
 * @property string|null $email
 * @property ClientStatus $status
 * @property string $phone
 * @property string|null $address
 * @property string|null $country
 * @property string|null $country_code
 * @property string|null $tax_id
 * @property string|null $nif
 * @property string|null $website
 * @property string|null $facebook_link
 * @property string|null $instagram_link
 * @property string|null $linkedin_link
 * @property string|null $twitter_link
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Collection<int, InvoiceEloquentModel> $invoices
 * @property-read Collection<int, ProductEloquentModel> $products
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> applyFilters(ClientFilterData $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClientEloquentModel withoutTrashed()
 * @method static ClientFactory factory($count = null, $state = [])
 *
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static Builder<static>|ClientEloquentModel whereAddress($value)
 * @method static Builder<static>|ClientEloquentModel whereClientName($value)
 * @method static Builder<static>|ClientEloquentModel whereCountry($value)
 * @method static Builder<static>|ClientEloquentModel whereCountryCode($value)
 * @method static Builder<static>|ClientEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|ClientEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|ClientEloquentModel whereEmail($value)
 * @method static Builder<static>|ClientEloquentModel whereFacebookLink($value)
 * @method static Builder<static>|ClientEloquentModel whereId($value)
 * @method static Builder<static>|ClientEloquentModel whereInstagramLink($value)
 * @method static Builder<static>|ClientEloquentModel whereLinkedinLink($value)
 * @method static Builder<static>|ClientEloquentModel whereNif($value)
 * @method static Builder<static>|ClientEloquentModel whereNotes($value)
 * @method static Builder<static>|ClientEloquentModel wherePhone($value)
 * @method static Builder<static>|ClientEloquentModel whereStatus($value)
 * @method static Builder<static>|ClientEloquentModel whereTaxId($value)
 * @method static Builder<static>|ClientEloquentModel whereTwitterLink($value)
 * @method static Builder<static>|ClientEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|ClientEloquentModel whereUserId($value)
 * @method static Builder<static>|ClientEloquentModel whereUuid($value)
 * @method static Builder<static>|ClientEloquentModel whereWebsite($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'uuid',
    'user_id',
    'client_name',
    'email',
    'status',
    'phone',
    'address',
    'country',
    'country_code',
    'tax_id',
    'nif',
    'website',
    'facebook_link',
    'instagram_link',
    'linkedin_link',
    'twitter_link',
    'notes',
])]
#[Hidden(['id'])]
final class ClientEloquentModel extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'clients';

    /** @var list<string> */
    private const array SORTABLE = ['created_at', 'updated_at', 'client_name', 'status'];

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
    }

    protected static function booted(): void
    {
        self::creating(function (self $client): void {
            if (empty($client->uuid)) {
                $client->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The invoices issued to this client.
     *
     * Inverse of `InvoiceEloquentModel::client()` — declared because
     * `invoices.client_id` is a foreign key and every FK in this project
     * carries both sides of the relation. The FK is `restrictOnDelete`, so a
     * client with invoices cannot be hard-deleted out from under them.
     *
     * @return HasMany<InvoiceEloquentModel, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(InvoiceEloquentModel::class, 'client_id');
    }

    /**
     * The catalog products commissioned by this client (an in-company course,
     * a video series produced for them).
     *
     * Inverse of `ProductEloquentModel::client()` — declared because
     * `products.client_id` is a foreign key and every FK in this project
     * carries both sides of the relation.
     *
     * @return HasMany<ProductEloquentModel, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(ProductEloquentModel::class, 'client_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'client_name',
                'email',
                'status',
                'phone',
                'address',
                'country',
                'country_code',
                'tax_id',
                'nif',
                'website',
                'facebook_link',
                'instagram_link',
                'linkedin_link',
                'twitter_link',
                'notes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('clients.client');
    }

    /**
     * The single source of truth for the admin list query AND the export query
     * (BACKEND-PHP §5.2) — `search` over the identifying columns, `status` as the
     * soft-delete axis (`active` / `deleted` / all), an inclusive `created_at`
     * window, and a whitelisted sort.
     */
    public function scopeApplyFilters(Builder $query, ClientFilterData $f): Builder
    {
        $sortField = in_array($f->sortField, self::SORTABLE, true) ? $f->sortField : 'created_at';

        return $query
            ->when($f->search, fn (Builder $q, string $s) => $q->where(function (Builder $q) use ($s): void {
                $q->where('client_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('tax_id', 'like', "%{$s}%")
                    ->orWhere('nif', 'like', "%{$s}%");
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
