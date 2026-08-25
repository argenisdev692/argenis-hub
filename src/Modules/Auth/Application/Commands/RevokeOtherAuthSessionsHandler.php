<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Commands;

use Modules\Auth\Domain\Ports\AuthSessionTrackerPort;
use Modules\Auth\Domain\Ports\TrustedDevicePort;

/**
 * "Sign out everywhere else" (FR-14, and the revocation half of US-03).
 *
 * Also drops the trusted-device marker: a user reacting to suspicious access
 * expects every shortcut around the 2FA challenge to disappear with the
 * sessions, not to survive them.
 */
final readonly class RevokeOtherAuthSessionsHandler
{
    public function __construct(
        private AuthSessionTrackerPort $sessions,
        private TrustedDevicePort $trustedDevices,
    ) {}

    /**
     * @return int number of sessions revoked
     */
    #[\NoDiscard('The revoked count is reported back to the user.')]
    public function handle(string $userUuid, string $currentSessionId): int
    {
        $revoked = $this->sessions->revokeOthers($userUuid, $currentSessionId);

        $this->trustedDevices->forget();

        return $revoked;
    }
}
