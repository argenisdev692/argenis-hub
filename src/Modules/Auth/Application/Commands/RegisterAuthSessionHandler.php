<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Commands;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Auth\Domain\Events\NewDeviceDetected;
use Modules\Auth\Domain\Ports\AuthSessionTrackerPort;
use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;

/**
 * Tracks a newly authenticated session and raises {@see NewDeviceDetected} the
 * first time a device fingerprint is seen for the user (FR-14, FR-15).
 */
final readonly class RegisterAuthSessionHandler
{
    public function __construct(
        private AuthSessionTrackerPort $sessions,
        private Dispatcher $events,
    ) {}

    /**
     * @return bool true when the login came from a device not seen before
     */
    #[\NoDiscard('The new-device flag drives the alert email and the audit entry.')]
    public function handle(
        string $userUuid,
        string $sessionId,
        ?string $ipAddress,
        ?string $userAgent,
    ): bool {
        $device = DeviceFingerprint::fromRequestSignals($userAgent, $ipAddress);

        $isNewDevice = $this->sessions->register($userUuid, $sessionId, $device, $ipAddress, $userAgent);

        if ($isNewDevice) {
            $this->events->dispatch(new NewDeviceDetected(
                userUuid: $userUuid,
                device: $device,
                ipAddress: $ipAddress,
                userAgent: $userAgent,
            ));
        }

        return $isNewDevice;
    }
}
