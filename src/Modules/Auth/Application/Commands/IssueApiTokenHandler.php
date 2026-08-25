<?php

declare(strict_types=1);

namespace Modules\Auth\Application\Commands;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Auth\Application\DTOs\ApiTokenData;

/**
 * Mints a Sanctum personal access token, rotating whatever the same device held
 * before (US-07 / clarify Q6).
 *
 * Rotation is per device name, not global: revoking every token would sign the
 * user out of their other devices on each login, which is a different feature
 * ("sign out everywhere") and a surprising side effect of logging in here.
 *
 * The lifetime mirrors the web idle lifetimes, so a stolen bearer token is
 * never longer-lived than a stolen session cookie. Privileged roles get the
 * shorter leash.
 */
final readonly class IssueApiTokenHandler
{
    /**
     * @param  list<string>  $abilities
     */
    #[\NoDiscard('The freshly minted token is the only copy — it cannot be read back.')]
    public function handle(User $user, string $deviceName, array $abilities = ['*']): ApiTokenData
    {
        $user->tokens()->where('name', $deviceName)->delete();

        $expiresAt = Carbon::now()->addMinutes($this->ttlMinutesFor($user));

        $token = $user->createToken($deviceName, $abilities, $expiresAt);

        return new ApiTokenData(
            accessToken: $token->plainTextToken,
            tokenType: 'Bearer',
            expiresAt: $expiresAt->toIso8601String(),
            abilities: $abilities,
        );
    }

    private function ttlMinutesFor(User $user): int
    {
        /** @var list<string> $privilegedRoles */
        $privilegedRoles = config('auth-security.privileged_roles', []);

        return $user->hasAnyRole($privilegedRoles)
            ? (int) config('auth-security.api_tokens.privileged_ttl_minutes')
            : (int) config('auth-security.api_tokens.ttl_minutes');
    }
}
