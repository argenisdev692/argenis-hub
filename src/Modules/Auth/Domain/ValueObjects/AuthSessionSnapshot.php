<?php

declare(strict_types=1);

namespace Modules\Auth\Domain\ValueObjects;

use Modules\Auth\Domain\Ports\AuthSessionTrackerPort;

/**
 * Immutable, transport-free view of one tracked session.
 *
 * Exists so {@see AuthSessionTrackerPort} can return
 * session data without the Domain depending on Eloquent (persistence) or on a
 * Spatie Data DTO (Application). The Application layer maps it to the HTTP DTO.
 *
 * Dates are ISO-8601 strings, never Carbon — see BACKEND-PHP §5 "Date Handling".
 */
final readonly class AuthSessionSnapshot
{
    public function __construct(
        public string $uuid,
        public ?string $ipAddress,
        public ?string $userAgent,
        public string $deviceHash,
        public ?string $lastSeenAt,
        public string $createdAt,
        public bool $isCurrent,
    ) {}
}
