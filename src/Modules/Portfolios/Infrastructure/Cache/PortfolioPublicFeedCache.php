<?php

declare(strict_types=1);

namespace Modules\Portfolios\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;

/**
 * Single source of the public portfolio feed's cache key.
 *
 * Named here, not inline in the query handler, for the same reason the Services
 * module's `ServicePublicFeedCache` exists: one place declares the key, the same
 * place flushes it, and every caller — `ListPublicPortfoliosHandler`, the model
 * `saved` / `deleted` / `restored` hooks — shares that one definition instead of
 * repeating a string literal.
 */
final class PortfolioPublicFeedCache
{
    private const string CACHE_KEY = 'portfolios.public_feed';

    public const string PUBLIC_CACHE_KEY = self::CACHE_KEY;

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
