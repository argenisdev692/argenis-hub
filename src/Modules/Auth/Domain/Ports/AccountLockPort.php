<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Ports;

use DateTimeImmutable;

/**
 * Durable brute-force lock on an account (FR-05).
 *
 * Keyed by email rather than user id: the login form submits an identity that
 * may not resolve to a user, and the lock must behave identically either way so
 * the response never becomes a user-enumeration oracle.
 */
interface AccountLockPort
{
    public function lock(string $email, DateTimeImmutable $until): void;

    /**
     * The moment the lock expires, or null when the account is not locked.
     */
    public function lockedUntil(string $email): ?DateTimeImmutable;

    public function release(string $email): void;
}
