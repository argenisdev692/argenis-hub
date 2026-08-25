<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\Events;

use DateTimeImmutable;
use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;

/**
 * Raised on a successful login from a device fingerprint never before seen for
 * this user (FR-15). Drives the new-device alert email.
 */
final readonly class NewDeviceDetected
{
    public function __construct(
        public string $userUuid,
        public DeviceFingerprint $device,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public DateTimeImmutable $occurredAt = new DateTimeImmutable,
    ) {}
}
