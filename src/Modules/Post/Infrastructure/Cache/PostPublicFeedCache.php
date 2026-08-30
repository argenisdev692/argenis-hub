<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Cache;

use Illuminate\Support\Facades\Cache;
use Modules\Blog\Infrastructure\Cache\BlogCategoryPublicFeedCache;
use Modules\Post\Application\Queries\GetPublicPostHandler;
use Modules\Post\Application\Queries\ListPublicPostsHandler;
use Modules\Post\Domain\Ports\PostPublicFeedCachePort;
use Throwable;

/**
 * Remember + invalidation for the public post feed / detail cache (see
 * {@see ListPublicPostsHandler} / {@see GetPublicPostHandler}). Called by every
 * Command handler that mutates a post, so the anonymous landing-page feed
 * never serves stale data past a save. Also busts the Blog module's public
 * category cache, since a category's `posts_count` changes whenever a post's
 * status or category assignment changes.
 *
 * Tag-based flush degrades silently when the cache store doesn't support tags
 * (BACKEND-PHP §5 Cache Management). A monotonic version key always bumps on
 * flush so plain-key fallbacks (e.g. CACHE_STORE=array in tests) invalidate too.
 *
 * Cached payloads MUST be plain arrays — never Eloquent models / paginators.
 * Serializing models + accessors across Redis caused sticky 500s after the
 * first warm (same class of bug as blog categories / portfolios).
 */
final class PostPublicFeedCache implements PostPublicFeedCachePort
{
    public const string PUBLIC_TAG = 'posts_public';

    private const string VERSION_KEY = 'posts.public.cache_version';

    private const int TTL_MINUTES = 5;

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

        try {
            return Cache::tags([self::PUBLIC_TAG])->remember($versionedKey, $ttl, $callback);
        } catch (Throwable) {
            return Cache::remember($versionedKey, $ttl, $callback);
        }
    }

    public function flush(): void
    {
        try {
            Cache::tags([self::PUBLIC_TAG])->flush();
        } catch (Throwable) {
            // Store doesn't support tags — version bump below still invalidates.
        }

        Cache::forever(self::VERSION_KEY, ((int) Cache::get(self::VERSION_KEY, 1)) + 1);

        // A post mutation changes each category's embedded published posts /
        // count, so bust the sibling public feed too.
        BlogCategoryPublicFeedCache::flushStatically();
    }
}
