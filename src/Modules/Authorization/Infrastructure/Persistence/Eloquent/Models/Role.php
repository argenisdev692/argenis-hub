<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Eloquent\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Authorization\Application\DTOs\RoleFilterData;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Application Role model. Extends Spatie's role (so `assignRole`, `hasRole`,
 * `syncPermissions` and the permission cache keep working) and layers on:
 *   - a public `uuid` for route binding (never expose the sequential id),
 *   - `SoftDeletes` so "suspend/restore" is lossless AND hidden from runtime
 *     authorization checks (the global scope excludes trashed roles everywhere),
 *   - an activity-log trail limited to `name`.
 *
 * `config/permission.php` points `models.role` here so the whole app resolves this
 * class — otherwise a suspended role would still grant access at runtime.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read int|null $permissions_count
 *
 * @mixin \Eloquent
 */
final class Role extends SpatieRole
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    /**
     * Spatie's base model ships `$guarded = []` (everything but the primary key
     * is mass-assignable). This project mandates an explicit allowlist instead —
     * `uuid` stays fillable because the seeder mints it inside `firstOrCreate()`.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'guard_name', 'uuid'];

    /**
     * `uuid` is the public identifier; the auto-increment `id` never leaves the
     * backend (OWASP API3 — property-level authorization).
     *
     * @var list<string>
     */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (self $role): void {
            if (empty($role->uuid)) {
                $role->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * Reusable list filter (BACKEND-PHP §4.1). The `suspended` status is applied
     * by the repository via `onlyTrashed()`, so it is intentionally not repeated
     * here.
     *
     * @param  Builder<Role>  $query
     * @return Builder<Role>
     */
    public function scopeApplyFilters(Builder $query, RoleFilterData $filters): Builder
    {
        return $query
            ->when($filters->search !== null, fn (Builder $q): Builder => $q->where('name', 'like', "%{$filters->search}%"))
            ->when(
                $filters->dateFrom !== null && $filters->dateTo !== null,
                fn (Builder $q): Builder => $q->whereBetween('created_at', [
                    CarbonImmutable::parse($filters->dateFrom)->startOfDay(),
                    CarbonImmutable::parse($filters->dateTo)->endOfDay(),
                ]),
            )
            ->when(
                $filters->dateFrom !== null && $filters->dateTo === null,
                fn (Builder $q): Builder => $q->where('created_at', '>=', CarbonImmutable::parse($filters->dateFrom)->startOfDay()),
            )
            ->when(
                $filters->dateTo !== null && $filters->dateFrom === null,
                fn (Builder $q): Builder => $q->where('created_at', '<=', CarbonImmutable::parse($filters->dateTo)->endOfDay()),
            );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // `deleted_at` is cast by the SoftDeletes trait; no extra casts required.
        return [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('authorization.role');
    }

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }
}
