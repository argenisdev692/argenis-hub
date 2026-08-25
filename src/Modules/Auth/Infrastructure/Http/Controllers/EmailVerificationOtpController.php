<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\RedirectResponse;
use Modules\Auth\Infrastructure\Http\Requests\VerifyEmailOtpRequest;
use Modules\Auth\Infrastructure\OneTimePasswords\OneTimePasswordVerifier;

/**
 * Verifies an email address with a 6-digit code instead of a signed link (FR-02).
 *
 * Runs behind `auth`: the account already exists and is signed in but blocked by
 * the `verified` middleware, so the code is redeemed against a known user rather
 * than an address supplied by whoever posts the form — which removes the
 * enumeration surface a guest flow would have.
 *
 * Issuing and re-issuing codes stays on Fortify's
 * `POST /email/verification-notification` route: `User::sendEmailVerificationNotification()`
 * is overridden to send a code, so there is nothing to duplicate here.
 */
final readonly class EmailVerificationOtpController
{
    public function __construct(
        private OneTimePasswordVerifier $oneTimePasswords,
        private Dispatcher $events,
    ) {}

    public function store(VerifyEmailOtpRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(config('fortify.home'));
        }

        $this->oneTimePasswords->assertConsumed($user, $request->code());

        if ($user->markEmailAsVerified()) {
            $this->events->dispatch(new Verified($user));
        }

        return redirect()
            ->intended(config('fortify.home'))
            ->with('status', __('Your email address has been verified.'));
    }
}
