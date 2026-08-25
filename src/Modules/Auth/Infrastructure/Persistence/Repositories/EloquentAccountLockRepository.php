<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Persistence\Repositories;

use App\Models\User;
use DateTimeImmutable;
use Modules\Auth\Domain\Ports\AccountLockPort;

/**
 * Persists the brute-force lock on the user row so it survives a cache flush,
 * a deploy, or a worker restart — and stays queryable for incident review.
 *
 * A lock on an address that matches no user is silently ignored: there is
 * nothing to protect, and creating a placeholder row would turn the login form
 * into a user-enumeration oracle.
 */
final readonly class EloquentAccountLockRepository implements AccountLockPort
{
    public function lock(string $email, DateTimeImmutable $until): void
    {
        User::query()
            ->where('email', $email)
            ->update(['locked_until' => $until]);
    }

    public function lockedUntil(string $email): ?DateTimeImmutable
    {
        $lockedUntil = User::query()
            ->where('email', $email)
            ->value('locked_until');

        if ($lockedUntil === null) {
            return null;
        }

        $expiresAt = new DateTimeImmutable((string) $lockedUntil);

        return $expiresAt > new DateTimeImmutable ? $expiresAt : null;
    }

    public function release(string $email): void
    {
        User::query()
            ->where('email', $email)
            ->whereNotNull('locked_until')
            ->update(['locked_until' => null]);
    }
}
