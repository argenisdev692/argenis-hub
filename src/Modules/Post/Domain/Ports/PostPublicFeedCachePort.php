<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

/**
 * Anonymous public post feed / detail cache (BACKEND-PHP §5 Cache Management).
 * Application handlers depend on this port; Infrastructure owns Redis-tag +
 * versioned-key remember/flush.
 */
interface PostPublicFeedCachePort
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
