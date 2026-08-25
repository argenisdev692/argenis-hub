<?php

declare(strict_types=1);

namespace Modules\Auth\Application\DTOs;

use Modules\Auth\Domain\ValueObjects\AuthSessionSnapshot;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Response shape for the active-sessions list (FR-14).
 *
 * Allowlist by construction (OWASP §12): the device hash, the internal id and
 * the framework session id never reach the client — only what the user needs to
 * recognise a session and revoke it.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AuthSessionData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
        public readonly ?string $lastSeenAt,
        public readonly string $createdAt,
        public readonly bool $isCurrent,
    ) {}

    public static function fromSnapshot(AuthSessionSnapshot $snapshot): self
    {
        return new self(
            uuid: $snapshot->uuid,
            ipAddress: $snapshot->ipAddress,
            userAgent: $snapshot->userAgent,
            lastSeenAt: $snapshot->lastSeenAt,
            createdAt: $snapshot->createdAt,
            isCurrent: $snapshot->isCurrent,
        );
    }
}
