<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Eloquent\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Authorization\Application\DTOs\PermissionFilterData;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Application Permission model — see {@see Role} for the rationale behind the
 * `uuid` + `SoftDeletes` + activity-log additions. `config/permission.php` points
 * `models.permission` here so a suspended permission is excluded from Spatie's
 * cached permission set as well as the management list.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @mixin \Eloquent
 */
final class Permission extends SpatiePermission
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    /**
     * Explicit allowlist replacing Spatie's `$guarded = []` — see {@see Role}.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'guard_name', 'uuid'];

    /**
     * @var list<string>
     */
    protected $hidden = ['id'];

    protected static function booted(): void
    {
        self::creating(function (self $permission): void {
            if (empty($permission->uuid)) {
                $permission->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * @param  Builder<Permission>  $query
     * @return Builder<Permission>
     */
    public function scopeApplyFilters(Builder $query, PermissionFilterData $filters): Builder
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
        return [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('authorization.permission');
    }

    protected static function newFactory(): PermissionFactory
    {
        return PermissionFactory::new();
    }
}
