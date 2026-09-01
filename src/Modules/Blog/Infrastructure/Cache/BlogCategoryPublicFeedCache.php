<?php

declare(strict_types=1);

namespace Modules\Blog\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;
use Modules\Blog\Application\Queries\ListPublicBlogCategoriesHandler;
use Modules\Blog\Domain\Ports\BlogCategoryPublicFeedCachePort;
use Modules\Post\Infrastructure\Cache\PostPublicFeedCache;
use Throwable;

/**
 * Remember + invalidation for the anonymous public blog-category feed (see
 * {@see ListPublicBlogCategoriesHandler}). Every Command handler that mutates a
 * category flushes it through the {@see BlogCategoryPublicFeedCachePort}, and the
 * Post module flushes it too (a category's embedded published posts / count
 * change whenever a post's status or category assignment changes).
 *
 * Tag-based flush degrades silently when the cache store doesn't support tags
 * (BACKEND-PHP §5). A monotonic version key always bumps on flush so plain-key
 * fallbacks (e.g. CACHE_STORE=array in tests) invalidate too — same strategy as
 * {@see PostPublicFeedCache}.
 */
final readonly class BlogCategoryPublicFeedCache implements BlogCategoryPublicFeedCachePort
{
    public const string PUBLIC_TAG = 'blog_categories_public';

    private const string VERSION_KEY = 'blog_categories.public.cache_version';

    private const int TTL_MINUTES = 10;

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function remember(string $key, callable $callback): mixed
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $versionedKey = "{$key}.v{$version}";
        $ttl = now()->addMinutes(self::TTL_MINUTES);

        // The port accepts any `callable` so the Domain stays free of a concrete
        // type; Laravel's `Repository::remember()` type-hints `Closure`. Promote
        // once here, at the adapter boundary, via first-class callable syntax.
        $closure = $callback(...);

        try {
            return Cache::tags([self::PUBLIC_TAG])->remember($versionedKey, $ttl, $closure);
        } catch (Throwable) {
            // Array/file stores reject tags — the version bump on flush still
            // invalidates the plain key.
            return Cache::remember($versionedKey, $ttl, $closure);
        }
    }

    public function flush(): void
    {
        self::invalidate();
    }

    /**
     * Static entry point kept for the Post module's cross-feed invalidation
     * (`PostPublicFeedCache::flush()`), which has no container to resolve the
     * port from.
     */
    public static function flushStatically(): void
    {
        self::invalidate();
    }

    private static function invalidate(): void
    {
        try {
            Cache::tags([self::PUBLIC_TAG])->flush();
        } catch (Throwable) {
            // Store doesn't support tags — the version bump below still invalidates.
        }

        Cache::forever(self::VERSION_KEY, ((int) Cache::get(self::VERSION_KEY, 1)) + 1);
    }
}
