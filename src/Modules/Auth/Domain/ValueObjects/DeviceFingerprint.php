<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\ValueObjects;

use Filter\FilterFailedException;
use InvalidArgumentException;

/**
 * Stable, non-reversible signal identifying the device a session was opened from.
 *
 * Used for two things only: deciding whether a login comes from a device the
 * user has seen before (FR-15) and binding the "trust this device" cookie to a
 * single browser (FR-08). It is intentionally coarse — a truncated SHA-256 over
 * the user agent plus the /24 (or /64) network of the client IP — so that a
 * roaming user on the same laptop is not alerted on every DHCP lease, while a
 * genuinely new browser or network is.
 *
 * Never stores raw PII: only the digest leaves this object.
 *
 * Property hooks are NOT used here: the readonly-hooks RFC was DECLINED for
 * PHP 8.5, so a hooked property may not be readonly. Invariants are enforced in
 * the constructor instead, which keeps the value object immutable.
 */
final readonly class DeviceFingerprint
{
    private const int HASH_LENGTH = 64;

    public function __construct(public string $hash)
    {
        if (! preg_match('/^[0-9a-f]{'.self::HASH_LENGTH.'}$/', $hash)) {
            throw new InvalidArgumentException('Device fingerprint must be a 64 character hexadecimal digest.');
        }
    }

    /**
     * Derive a fingerprint from the request signals available at login time.
     */
    public static function fromRequestSignals(?string $userAgent, ?string $ipAddress): self
    {
        $rawAgent = (string) $userAgent;

        $normalizedAgent = $rawAgent
            |> trim(...)
            |> (static fn (string $agent): string => $agent === '' ? 'unknown-agent' : $agent)
            |> (static fn (string $agent): string => mb_substr($agent, 0, 512));

        return new self(hash('sha256', $normalizedAgent.'|'.self::network($ipAddress)));
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->hash, $other->hash);
    }

    public function __toString(): string
    {
        return $this->hash;
    }

    /**
     * Collapse an address to its network so that a changing host address within
     * the same network is not treated as a new device.
     */
    private static function network(?string $ipAddress): string
    {
        $address = trim((string) $ipAddress);

        if ($address === '') {
            return 'unknown-network';
        }

        // PHP 8.5's FILTER_THROW_ON_FAILURE raises Filter\FilterFailedException
        // — NOT \ValueError, which it only raises for a contradictory flag set.
        try {
            filter_var($address, FILTER_VALIDATE_IP, FILTER_THROW_ON_FAILURE);
        } catch (FilterFailedException) {
            return 'unknown-network';
        }

        return match (true) {
            str_contains($address, ':') => implode(':', array_slice(explode(':', $address), 0, 4)),
            default => implode('.', array_slice(explode('.', $address), 0, 3)),
        };
    }
}
