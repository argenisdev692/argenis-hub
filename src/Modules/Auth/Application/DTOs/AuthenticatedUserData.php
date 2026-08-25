<?php

declare(strict_types=1);

namespace Modules\Auth\Application\DTOs;

use App\Models\User;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The identity shape returned by `GET /api/auth/me` and embedded in the login
 * response.
 *
 * Allowlist by construction (OWASP §12): the internal id, the password hash,
 * the 2FA secret and the recovery codes are absent by omission rather than by
 * a `$hidden` list that a future column could slip past.
 *
 * Permissions — never roles — drive client-side UI gating, matching the web app.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AuthenticatedUserData extends Data
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $emailVerifiedAt,
        public readonly bool $twoFactorEnabled,
        public readonly array $roles,
        public readonly array $permissions,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            uuid: (string) $user->uuid,
            name: (string) $user->name,
            email: (string) $user->email,
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            twoFactorEnabled: $user->two_factor_confirmed_at !== null,
            roles: $user->getRoleNames()->all(),
            permissions: $user->getAllPermissions()->pluck('name')->all(),
        );
    }
}
