<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Ports;

use Modules\Auth\Domain\ValueObjects\AuthSessionSnapshot;
use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;

/**
 * Driver-agnostic tracking of a user's active sessions and devices (FR-14, FR-15).
 *
 * Independent of the configured session driver: the sessions list and the
 * new-device signal must behave the same on Redis (production) and on the
 * database/array drivers used locally and in tests.
 */
interface AuthSessionTrackerPort
{
    /**
     * Track a freshly authenticated session.
     *
     * @return bool true when this device fingerprint has never been seen for
     *              this user before — the caller turns that into the alert.
     */
    public function register(
        string $userUuid,
        string $sessionId,
        DeviceFingerprint $device,
        ?string $ipAddress,
        ?string $userAgent,
    ): bool;

    /**
     * Refresh the activity stamp of an already tracked session.
     *
     * @return bool false when this session id is not tracked yet — the caller
     *              turns that into a {@see self::register()} call. Registration
     *              cannot happen at login time: the framework regenerates the
     *              session id AFTER the login event, so the id known then is
     *              already stale by the next request.
     */
    public function touch(string $userUuid, string $sessionId): bool;

    /**
     * Mark the session tied to this framework session id as ended.
     */
    public function release(string $userUuid, string $sessionId): void;

    /**
     * @return bool true when a session owned by this user was revoked
     */
    public function revoke(string $userUuid, string $sessionUuid): bool;

    /**
     * @return int number of sessions revoked
     */
    public function revokeOthers(string $userUuid, string $currentSessionId): int;

    /**
     * @return list<AuthSessionSnapshot>
     */
    public function activeFor(string $userUuid, string $currentSessionId): array;

    public function isRevoked(string $sessionId): bool;
}
