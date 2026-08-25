<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $guard_name
 */
class Permission extends SpatiePermission
{
    use SoftDeletes;

    public $fillable = ['name', 'guard_name', 'uuid'];

    protected static function booted(): void
    {
        static::creating(function (Permission $permission): void {
            if ($permission->uuid === null) {
                $permission->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * Remove the permission cache immediately on save, not only on flush.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
        static::deleted(fn () => app(PermissionRegistrar::class)->forgetCachedPermissions());
    }
}
