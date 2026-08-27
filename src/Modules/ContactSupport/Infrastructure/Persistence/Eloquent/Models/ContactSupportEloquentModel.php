<?php

declare(strict_types=1);

namespace Modules\ContactSupport\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\ContactSupportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\ContactSupport\Application\DTOs\ContactSupportFilterData;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $user_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $subject
 * @property string $message
 * @property bool $sms_consent
 * @property bool $readed
 * @property bool $is_spam
 * @property int $spam_score
 * @property array<int, string>|null $spam_reasons
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> applyFilters(ContactSupportFilterData $filters)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactSupportEloquentModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactSupportEloquentModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactSupportEloquentModel onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactSupportEloquentModel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactSupportEloquentModel withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ContactSupportEloquentModel withoutTrashed()
 * @method static ContactSupportFactory factory($count = null, $state = [])
 *
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static Builder<static>|ContactSupportEloquentModel whereCreatedAt($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereDeletedAt($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereEmail($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereFirstName($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereId($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereIsSpam($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereLastName($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereMessage($value)
 * @method static Builder<static>|ContactSupportEloquentModel wherePhone($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereReaded($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereSmsConsent($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereSpamReasons($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereSpamScore($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereSubject($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereUpdatedAt($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereUserId($value)
 * @method static Builder<static>|ContactSupportEloquentModel whereUuid($value)
 *
 * @mixin \Eloquent
 */
#[Fillable([
    'uuid',
    'user_id',
    'first_name',
    'last_name',
    'email',
    'phone',
    'subject',
    'message',
    'sms_consent',
    'readed',
    'is_spam',
    'spam_score',
    'spam_reasons',
])]
#[Hidden(['id'])]
final class ContactSupportEloquentModel extends Model
{
    /** @use HasFactory<ContactSupportFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'contact_supports';

    /** @var list<string> */
    private const array SORTABLE = ['created_at', 'updated_at', 'subject', 'email', 'readed', 'is_spam'];

    protected static function newFactory(): ContactSupportFactory
    {
        return ContactSupportFactory::new();
    }

    protected static function booted(): void
    {
        self::creating(function (self $support): void {
            if (empty($support->uuid)) {
                $support->uuid = (string) Str::uuid7();
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sms_consent' => 'boolean',
            'readed' => 'boolean',
            'is_spam' => 'boolean',
            'spam_score' => 'integer',
            'spam_reasons' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'first_name',
                'last_name',
                'email',
                'phone',
                'subject',
                'message',
                'sms_consent',
                'readed',
                'is_spam',
                'spam_score',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('contact-support.contact-support');
    }

    /**
     * Single source of truth for the admin inbox list AND the export query
     * (BACKEND-PHP §5.2): free-text `search` over the identifying columns, the
     * soft-delete `status` axis (`active` / `deleted` / all), the `readed` and
     * `is_spam` inbox toggles (each backed by its own index), an inclusive
     * `created_at` window, and a whitelisted sort.
     */
    public function scopeApplyFilters(Builder $query, ContactSupportFilterData $f): Builder
    {
        $sortField = in_array($f->sortField, self::SORTABLE, true) ? $f->sortField : 'created_at';

        return $query
            ->when($f->search, fn (Builder $q, string $s) => $q->where(function (Builder $q) use ($s): void {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('subject', 'like', "%{$s}%");
            }))
            ->when($f->status === 'active', fn (Builder $q) => $q->whereNull('deleted_at'))
            ->when($f->status === 'deleted', fn (Builder $q) => $q->onlyTrashed())
            ->when($f->status === '' || $f->status === null, fn (Builder $q) => $q->withTrashed())
            ->when($f->readed !== null, fn (Builder $q) => $q->where('readed', $f->readed))
            ->when($f->isSpam !== null, fn (Builder $q) => $q->where('is_spam', $f->isSpam))
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
