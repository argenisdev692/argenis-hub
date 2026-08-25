<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A retired password hash, kept only long enough to reject reuse (FR-12).
 *
 * @internal
 *
 * Two deliberate deviations from the standard model template, both for the same
 * reason — this row IS credential material:
 *   1. No `SoftDeletes`. Pruned hashes must leave the database; a `deleted_at`
 *      tombstone would keep every historical password hash readable forever.
 *   2. No `LogsActivity`. An activity entry would copy the hash (or its
 *      before/after pair) into the audit log, which must never hold secrets
 *      (OWASP §9 / BACKEND-PHP §11).
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $password_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
#[Table('password_histories')]
#[Fillable(['uuid', 'user_id', 'password_hash'])]
#[Hidden(['id', 'user_id', 'password_hash'])]
final class PasswordHistoryEloquentModel extends Model
{
    protected static function booted(): void
    {
        self::creating(function (self $entry): void {
            $entry->uuid ??= (string) Str::uuid7();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
