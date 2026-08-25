<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Middleware;

use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Modules\Auth\Domain\Ports\TrustedDevicePort;
use Modules\Auth\Domain\ValueObjects\DeviceFingerprint;

/**
 * Lets a browser the user has explicitly trusted skip the TOTP challenge for the
 * configured window (FR-08).
 *
 * Bound to Fortify's `RedirectsIfTwoFactorAuthenticatable` contract rather than
 * forking the login pipeline, so every other Fortify guarantee — credential
 * validation, failed-attempt events, password rehashing — is inherited
 * untouched.
 *
 * The parent's `handle()` is deliberately NOT delegated to: it would validate
 * the credentials a second time, paying for another Argon2id verification on
 * every successful login. The two-factor decision below mirrors the parent's
 * conditions exactly, with the trusted-device check inserted as the last gate.
 *
 * Not `readonly`: the parent class is not.
 */
final class TrustedDeviceAwareTwoFactorRedirect extends RedirectIfTwoFactorAuthenticatable
{
    public function __construct(
        StatefulGuard $guard,
        LoginRateLimiter $limiter,
        private readonly TrustedDevicePort $trustedDevices,
    ) {
        parent::__construct($guard, $limiter);
    }

    /**
     * @param  Request  $request
     * @param  callable  $next
     */
    public function handle($request, $next): mixed
    {
        $user = $this->validateCredentials($request);

        if (! $this->requiresTwoFactorChallenge($user)) {
            return $next($request);
        }

        $device = DeviceFingerprint::fromRequestSignals($request->userAgent(), $request->ip());

        if ($this->trustedDevices->isTrusted((string) $user->uuid, $device)) {
            return $next($request);
        }

        return $this->twoFactorChallengeResponse($request, $user);
    }

    /**
     * Mirrors the parent's branch: when Fortify requires explicit confirmation,
     * an unconfirmed enrolment must NOT gate the login.
     */
    private function requiresTwoFactorChallenge(mixed $user): bool
    {
        if ($user === null || ! in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)) {
            return false;
        }

        if ($user->two_factor_secret === null) {
            return false;
        }

        return ! Fortify::confirmsTwoFactorAuthentication() || $user->two_factor_confirmed_at !== null;
    }
}
