<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Ports;

/**
 * Rolling counter of consecutive failed logins for one identity.
 *
 * Deliberately separate from {@see AccountLockPort}: the counter is ephemeral
 * and lives in the cache (Redis in production, any store locally), while the
 * resulting lock is durable state on the user record. Keeping the two ports
 * apart is what lets each have exactly one adapter and one reason to change.
 */
interface LoginAttemptTrackerPort
{
    /**
     * Record one failure and return the new consecutive-failure count.
     */
    public function recordFailure(string $email): int;

    public function failures(string $email): int;

    public function clear(string $email): void;
}
