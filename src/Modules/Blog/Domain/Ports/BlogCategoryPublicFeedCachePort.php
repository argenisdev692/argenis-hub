<?php

declare(strict_types=1);

namespace Modules\Blog\Domain\Ports;

use Modules\Post\Domain\Ports\PostPublicFeedCachePort;

/**
 * Anonymous public blog-category feed cache (BACKEND-PHP §5 Cache Management).
 * Application handlers depend on this port; Infrastructure owns the Redis-tag +
 * versioned-key remember/flush mechanics. Mirrors
 * {@see PostPublicFeedCachePort} so both public
 * feeds share one caching contract.
 */
interface BlogCategoryPublicFeedCachePort
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function remember(string $key, callable $callback): mixed;

    public function flush(): void;
}
