<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Events;

use DateTimeImmutable;

/**
 * Raised when consecutive failed logins for one account cross the configured
 * threshold and the account is locked for a cooling-off window (FR-05).
 *
 * Carries the email rather than a user id on purpose: the lockout counter is
 * keyed by the submitted identity, which may not resolve to an existing user.
 */
final readonly class AccountLockedOut
{
    public function __construct(
        public string $email,
        public int $failedAttempts,
        public DateTimeImmutable $lockedUntil,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}
}
