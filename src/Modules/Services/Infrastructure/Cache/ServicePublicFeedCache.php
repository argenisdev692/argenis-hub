<?php

declare(strict_types=1);

namespace Modules\Services\Infrastructure\Cache;

use Database\Seeders\ServiceSeeder;
use Illuminate\Support\Facades\Cache;
use Modules\Services\Application\Queries\ListPublicServicesHandler;

/**
 * Single source of the public service feed's cache key.
 *
 * Named here, not inline in the query handler, for the same reason {@see
 * \Shared\Infrastructure\Company\CompanyProfile} owns the company cache key:
 * one place declares it, the same place flushes it, and every caller —
 * {@see ListPublicServicesHandler}, the
 * model's `saved`/`deleted` hooks, {@see ServiceSeeder} —
 * shares that one definition instead of repeating a string literal.
 */
final class ServicePublicFeedCache
{
    private const string CACHE_KEY = 'services.public_feed';

    public const string PUBLIC_CACHE_KEY = self::CACHE_KEY;

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
