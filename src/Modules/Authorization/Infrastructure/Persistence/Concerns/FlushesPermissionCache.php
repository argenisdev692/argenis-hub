<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Persistence\Concerns;

use Spatie\Permission\PermissionRegistrar;

/**
 * Spatie invalidates its permission cache automatically on individual model
 * `saved` / `deleted` events, but mass soft-delete / restore run as a single UPDATE
 * and fire no per-model events. Repositories mix this trait in and call
 * {@see self::flushPermissionCache()} after those bulk writes so authorization
 * checks never read a stale cache.
 */
trait FlushesPermissionCache
{
    protected function flushPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
