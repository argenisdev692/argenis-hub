<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $guard_name
 */
class Role extends SpatieRole
{
    use SoftDeletes;

    public $fillable = ['name', 'guard_name', 'uuid'];

    protected static function booted(): void
    {
        static::creating(function (Role $role): void {
            if ($role->uuid === null) {
                $role->uuid = (string) Str::uuid7();
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
