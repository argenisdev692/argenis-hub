<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\OneTimePasswords;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\OneTimePasswords\Enums\ConsumeOneTimePasswordResult;

/**
 * The single place where a 6-digit code is issued and redeemed.
 *
 * Both flows that use codes — email verification (FR-02) and password reset
 * (FR-11) — share this adapter so the expiry, the single-use guarantee, the
 * same-origin check and the failure messages can never drift apart.
 *
 * Failures are surfaced as validation errors on the submitting field, never as
 * exceptions that leak which of the six failure modes occurred: "expired",
 * "wrong code" and "no code issued" must look identical from outside.
 */
final readonly class OneTimePasswordVerifier
{
    public function issueTo(User $user): void
    {
        $user->sendOneTimePassword((int) config('auth-security.otp.expires_in_minutes'));
    }

    /**
     * @throws ValidationException when the code is missing, wrong, expired,
     *                             submitted from another browser, or rate limited
     */
    public function assertConsumed(User $user, string $code, string $attribute = 'code'): void
    {
        $result = $user->consumeOneTimePassword($code);

        if ($result->isOk()) {
            return;
        }

        throw ValidationException::withMessages([
            $attribute => [$this->messageFor($result)],
        ]);
    }

    private function messageFor(ConsumeOneTimePasswordResult $result): string
    {
        return match ($result) {
            ConsumeOneTimePasswordResult::RateLimitExceeded => __('Too many attempts. Please request a new code.'),
            default => __('That code is invalid or has expired. Please request a new one.'),
        };
    }
}
