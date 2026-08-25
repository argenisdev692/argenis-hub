<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Ports;

use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;

/**
 * "Trust this device for 30 days" — lets a browser skip the TOTP challenge
 * without weakening the account (FR-08).
 *
 * The trust marker is bound to BOTH the user and the device fingerprint, so a
 * stolen marker is useless on another browser, and it is revoked whenever 2FA is
 * disabled or the user signs out everywhere.
 */
interface TrustedDevicePort
{
    public function isTrusted(string $userUuid, DeviceFingerprint $device): bool;

    public function trust(string $userUuid, DeviceFingerprint $device): void;

    public function forget(): void;
}
