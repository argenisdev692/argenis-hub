<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Commands;

use Modules\Auth\Domain\Ports\TrustedDevicePort;
use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;

/**
 * Marks the current browser as trusted so it may skip the TOTP challenge for
 * the configured window (FR-08).
 */
final readonly class TrustCurrentDeviceHandler
{
    public function __construct(private TrustedDevicePort $trustedDevices) {}

    public function handle(string $userUuid, ?string $userAgent, ?string $ipAddress): void
    {
        $this->trustedDevices->trust(
            $userUuid,
            DeviceFingerprint::fromRequestSignals($userAgent, $ipAddress),
        );
    }
}
