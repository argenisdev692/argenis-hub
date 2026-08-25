<?php

declare(strict_types=1);

namespace Modules\Auth\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The bearer credential handed back by `POST /api/auth/login` and
 * `POST /api/auth/refresh`.
 *
 * `accessToken` is the ONLY moment the plain-text token exists — Sanctum stores
 * a SHA-256 digest and can never reproduce it. Losing this response means
 * logging in again, which is the intended property.
 *
 * The token is an opaque random string, NOT a JWT: it carries no claims and
 * must never be decoded client-side (OWASP §2).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ApiTokenData extends Data
{
    /**
     * @param  list<string>  $abilities
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly string $expiresAt,
        public readonly array $abilities,
    ) {}
}
