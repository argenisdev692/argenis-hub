<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Security;

use Illuminate\Contracts\Cookie\QueueingFactory as CookieJar;
use Illuminate\Http\Request;
use Modules\Auth\Domain\Ports\TrustedDevicePort;
use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;

/**
 * "Trust this device for 30 days" implemented as an encrypted, HTTP-only,
 * SameSite=Lax cookie (FR-08).
 *
 * The cookie is covered by the application's global cookie encryption, so its
 * payload is both tamper-proof and unreadable by the client. It carries no PII:
 * only the user uuid and the device digest, and it is honoured ONLY when BOTH
 * match — a marker copied to another browser produces a different fingerprint
 * and is therefore worthless.
 */
final readonly class CookieTrustedDeviceRegistry implements TrustedDevicePort
{
    public const string COOKIE_NAME = 'trusted_device';

    public function __construct(
        private Request $request,
        private CookieJar $cookies,
        private int $trustDays,
    ) {}

    public function isTrusted(string $userUuid, DeviceFingerprint $device): bool
    {
        $payload = $this->decodePayload($this->request->cookie(self::COOKIE_NAME));

        if ($payload === null) {
            return false;
        }

        return hash_equals($payload['user'], $userUuid)
            && hash_equals($payload['device'], $device->hash);
    }

    public function trust(string $userUuid, DeviceFingerprint $device): void
    {
        $value = json_encode(['user' => $userUuid, 'device' => $device->hash], JSON_THROW_ON_ERROR);

        $this->cookies->queue($this->cookies->make(
            name: self::COOKIE_NAME,
            value: $value,
            minutes: $this->trustDays * 24 * 60,
            path: null,
            domain: null,
            secure: $this->request->isSecure(),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        ));
    }

    public function forget(): void
    {
        $this->cookies->queue($this->cookies->forget(self::COOKIE_NAME));
    }

    /**
     * @return array{user: string, device: string}|null
     */
    private function decodePayload(mixed $cookie): ?array
    {
        if (! is_string($cookie) || $cookie === '') {
            return null;
        }

        try {
            $payload = json_decode($cookie, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($payload) || ! is_string($payload['user'] ?? null) || ! is_string($payload['device'] ?? null)) {
            return null;
        }

        return ['user' => $payload['user'], 'device' => $payload['device']];
    }
}
