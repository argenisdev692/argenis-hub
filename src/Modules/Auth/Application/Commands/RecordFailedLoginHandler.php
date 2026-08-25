<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Commands;

use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Modules\Auth\Domain\Events\AccountLockedOut;
use Modules\Auth\Domain\Ports\AccountLockPort;
use Modules\Auth\Domain\Ports\LoginAttemptTrackerPort;

/**
 * Counts consecutive failed logins for one identity and locks the account once
 * the threshold is crossed (FR-05).
 *
 * Keyed by account, not by IP: per-IP counting is trivially defeated by a
 * distributed attack (clarify Q4). The accepted trade-off is a self-healing
 * 15-minute window plus an audited lockout event.
 */
final readonly class RecordFailedLoginHandler
{
    public function __construct(
        private LoginAttemptTrackerPort $attempts,
        private AccountLockPort $locks,
        private Dispatcher $events,
    ) {}

    /**
     * @return int the consecutive-failure count after this attempt
     */
    #[\NoDiscard('The failure count decides whether the caller must surface a lockout.')]
    public function handle(
        string $email,
        int $threshold,
        int $lockMinutes,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): int {
        $normalizedEmail = $email
            |> trim(...)
            |> mb_strtolower(...);

        $failures = $this->attempts->recordFailure($normalizedEmail);

        if ($failures < $threshold) {
            return $failures;
        }

        $lockedUntil = (new DateTimeImmutable)->modify("+{$lockMinutes} minutes");

        $this->locks->lock($normalizedEmail, $lockedUntil);
        $this->attempts->clear($normalizedEmail);

        $this->events->dispatch(new AccountLockedOut(
            email: $normalizedEmail,
            failedAttempts: $failures,
            lockedUntil: $lockedUntil,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        ));

        return $failures;
    }
}
