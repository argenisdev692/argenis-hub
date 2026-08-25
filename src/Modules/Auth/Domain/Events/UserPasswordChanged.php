<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Events;

use DateTimeImmutable;

/**
 * Raised whenever a user's credential is replaced — self-service change, OTP
 * reset, or the Fortify password broker (FR-12, FR-13).
 *
 * One event for all three paths is what keeps the follow-up work (reuse
 * history, notification, signing other sessions out, audit entry) defined
 * exactly once instead of being re-implemented per entry point.
 *
 * Carries the hash, never the plaintext: the reactions only need something to
 * compare future passwords against.
 */
final readonly class UserPasswordChanged
{
    public function __construct(
        public string $userUuid,
        public string $hashedPassword,
        public bool $signOutOtherSessions = true,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}
}
