<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Database\Factories\AuthSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One tracked sign-in — the source of truth for the sessions list (FR-14) and
 * for new-device detection (FR-15), independent of the session driver.
 *
 * @internal
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $session_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $device_hash
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
#[Table('auth_sessions')]
#[Fillable([
    'uuid',
    'user_id',
    'session_id',
    'ip_address',
    'user_agent',
    'device_hash',
    'last_seen_at',
    'revoked_at',
])]
#[Hidden(['id', 'user_id', 'session_id'])]
final class AuthSessionEloquentModel extends Model
{
    /** @use HasFactory<AuthSessionFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * No SoftDeletes: `revoked_at` already models the end of a session's life.
     * Ended and stale rows are removed for good after the retention window —
     * keeping IP/user-agent pairs beyond it would be data collection without a
     * purpose.
     */
    use MassPrunable;

    private const int RETENTION_DAYS = 90;

    protected static function booted(): void
    {
        self::creating(function (self $session): void {
            $session->uuid ??= (string) Str::uuid7();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return Builder<$this> */
    public function prunable(): Builder
    {
        return self::query()
            ->where('revoked_at', '<=', now()->subDays(self::RETENTION_DAYS))
            ->orWhere('last_seen_at', '<=', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * Only revocations are worth an activity entry — `last_seen_at` is touched
     * on nearly every request and would drown the log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['revoked_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('auth.session');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AuthSessionFactory
    {
        return AuthSessionFactory::new();
    }
}
