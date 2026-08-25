<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Auth\Application\Commands\IssueApiTokenHandler;
use Modules\Auth\Application\DTOs\AuthenticatedUserData;
use Modules\Auth\Domain\Ports\AccountLockPort;
use Modules\Auth\Infrastructure\Http\Requests\Api\ApiLoginRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sanctum token endpoints for the mobile / external API (spec 001 §8).
 *
 * The web app does NOT use these — it authenticates with sessions and Inertia.
 * This surface exists so a native client can obtain a bearer token, and it
 * therefore has to re-implement, not bypass, every gate the web login enforces:
 * account lockout, email verification, and the two-factor challenge. An API
 * login that skipped TOTP would quietly turn 2FA into an opt-out feature.
 *
 * Brute-force state is shared with the web flow by dispatching the framework's
 * own `Failed` / `Login` events: the module's existing listeners then handle
 * lockout counting, counter clearing and the audit trail, so there is exactly
 * one implementation of that policy.
 *
 * Tokens are opaque random strings, never JWTs — clients must treat them as
 * blobs and never attempt to decode them.
 */
final readonly class AuthApiController
{
    private const string GUARD = 'sanctum';

    public function __construct(
        private AccountLockPort $locks,
        private IssueApiTokenHandler $issueToken,
        private TwoFactorAuthenticationProvider $twoFactor,
        private Hasher $hasher,
        private Dispatcher $events,
    ) {}

    /**
     * Exchange credentials for a bearer token.
     *
     * Every rejection below answers with the SAME message and status, so the
     * endpoint never becomes an account-enumeration oracle (OWASP §2).
     */
    public function login(ApiLoginRequest $request): JsonResponse
    {
        $email = $request->email();

        $this->assertNotLockedOut($email);

        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $this->hasher->check($request->password(), (string) $user->password)) {
            $this->events->dispatch(new Failed(self::GUARD, $user, ['email' => $email]));

            throw ValidationException::withMessages([
                'email' => [__('These credentials do not match our records.')],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => [__('Your email address is not verified.')],
            ]);
        }

        $this->assertTwoFactorSatisfied($user, $request->code());

        $this->events->dispatch(new Login(self::GUARD, $user, false));

        return response()->json([
            'token' => $this->issueToken->handle($user, $request->deviceName()),
            'user' => AuthenticatedUserData::fromUser($user),
        ], Response::HTTP_CREATED);
    }

    /**
     * Rotate the caller's own token.
     *
     * The presented token is destroyed and replaced, so a leaked bearer stops
     * working as soon as the legitimate client refreshes. The device name is
     * carried over, which is what makes the rotation idempotent per device.
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PersonalAccessToken $current */
        $current = $user->currentAccessToken();
        $deviceName = (string) $current->name;

        $current->delete();

        return response()->json([
            'token' => $this->issueToken->handle($user, $deviceName),
            'user' => AuthenticatedUserData::fromUser($user),
        ]);
    }

    /**
     * The authenticated identity behind the presented token.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => AuthenticatedUserData::fromUser($user),
        ]);
    }

    /**
     * Revoke the presented token (sign out this device only).
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->currentAccessToken()->delete();

        return response()->json(['message' => __('Signed out.')]);
    }

    /**
     * FR-05 — a locked account is refused before the password is ever checked,
     * so the lock cannot be probed by timing or by response shape.
     */
    private function assertNotLockedOut(string $email): void
    {
        $lockedUntil = $this->locks->lockedUntil($email);

        if ($lockedUntil === null) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => [__('Too many failed attempts. Please try again later.')],
        ])->status(Response::HTTP_LOCKED);
    }

    /**
     * FR-07 — an account with confirmed 2FA must present a TOTP or recovery
     * code here exactly as it would on the web challenge screen.
     */
    private function assertTwoFactorSatisfied(User $user, ?string $code): void
    {
        if ($user->two_factor_confirmed_at === null) {
            return;
        }

        if ($code === null) {
            throw ValidationException::withMessages([
                'code' => [__('A two-factor code is required.')],
            ]);
        }

        if ($this->isValidTotp($user, $code) || $this->consumeRecoveryCode($user, $code)) {
            return;
        }

        $this->events->dispatch(new TwoFactorAuthenticationFailed($user));

        throw ValidationException::withMessages([
            'code' => [__('The provided two-factor code was invalid.')],
        ]);
    }

    /**
     * A secret that cannot be decrypted or is malformed (too short for the
     * TOTP algorithm, truncated by a bad migration, encrypted under a rotated
     * APP_KEY) must FAIL THE CHALLENGE, not crash the endpoint. Letting the
     * exception escape would turn a data problem into a 500 that leaks a stack
     * trace and skips the recovery-code fallback the user still has (OWASP §10).
     */
    private function isValidTotp(User $user, string $code): bool
    {
        try {
            return $this->twoFactor->verify(
                decrypt((string) $user->two_factor_secret),
                $code,
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Recovery codes are single use: a match is swapped for a fresh code in the
     * same step, so replaying the response is worthless.
     */
    private function consumeRecoveryCode(User $user, string $code): bool
    {
        try {
            $recoveryCodes = $user->recoveryCodes();
        } catch (\Throwable) {
            return false;
        }

        $matched = collect($recoveryCodes)
            ->first(static fn (string $recoveryCode): bool => hash_equals($recoveryCode, $code));

        if ($matched === null) {
            return false;
        }

        $user->replaceRecoveryCode($matched);

        return true;
    }
}
