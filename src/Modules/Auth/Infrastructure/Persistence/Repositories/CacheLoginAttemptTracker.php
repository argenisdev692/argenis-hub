<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Cache\Repository as Cache;
use Modules\Auth\Domain\Ports\LoginAttemptTrackerPort;

/**
 * Consecutive-failure counter kept in the default cache store.
 *
 * Store-agnostic by design (clarify Q3): Redis in production, database or array
 * locally and in tests. The key is a digest of the submitted address so the
 * cache never holds a readable list of the emails people are trying to log in
 * with.
 */
final readonly class CacheLoginAttemptTracker implements LoginAttemptTrackerPort
{
    private const string KEY_PREFIX = 'auth:login-failures:';

    public function __construct(
        private Cache $cache,
        private int $windowMinutes,
    ) {}

    public function recordFailure(string $email): int
    {
        $key = $this->keyFor($email);
        $failures = $this->cache->get($key, 0) + 1;

        $this->cache->put($key, $failures, now()->addMinutes($this->windowMinutes));

        return $failures;
    }

    public function failures(string $email): int
    {
        return (int) $this->cache->get($this->keyFor($email), 0);
    }

    public function clear(string $email): void
    {
        $this->cache->forget($this->keyFor($email));
    }

    private function keyFor(string $email): string
    {
        return self::KEY_PREFIX.hash('sha256', mb_strtolower(trim($email)));
    }
}
