<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Commands;

use Modules\Auth\Domain\Ports\AccountLockPort;
use Modules\Auth\Domain\Ports\LoginAttemptTrackerPort;

/**
 * Resets the brute-force state for an identity after a successful login, so a
 * user who simply mistyped their password a few times is never punished later.
 */
final readonly class ClearLoginFailuresHandler
{
    public function __construct(
        private LoginAttemptTrackerPort $attempts,
        private AccountLockPort $locks,
    ) {}

    public function handle(string $email): void
    {
        $normalizedEmail = $email
            |> trim(...)
            |> mb_strtolower(...);

        $this->attempts->clear($normalizedEmail);
        $this->locks->release($normalizedEmail);
    }
}
