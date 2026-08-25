<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use Modules\Auth\Infrastructure\Http\Requests\VerifyTwoFactorEmailCodeRequest;
use Modules\Auth\Infrastructure\OneTimePasswords\OneTimePasswordVerifier;

/**
 * Emailed 6-digit code as an alternative second factor at the login challenge.
 *
 * An authenticator app is the strongest of the three options and stays the
 * default, but a user who has lost their phone should not be pushed straight to
 * a single-use recovery code. This is the middle rung: still a second factor,
 * still bound to something the account owner controls.
 *
 * Both endpoints run as `guest`. The first factor has already been cleared —
 * Fortify wrote `login.id` to the session only after verifying the password —
 * so a challenged user exists without anyone being authenticated yet. The
 * session key is the whole authorisation story here, which is why neither
 * endpoint accepts an email address: there is nothing to enumerate.
 *
 * The code itself is issued and redeemed by the same
 * {@see OneTimePasswordVerifier} that email verification and password reset
 * use, so expiry, single-use and the deliberately uniform failure message
 * cannot drift between flows.
 */
final readonly class TwoFactorEmailCodeController
{
    public function __construct(
        private OneTimePasswordVerifier $oneTimePasswords,
        private StatefulGuard $guard,
        private Dispatcher $events,
    ) {}

    /**
     * Email a fresh code to the challenged account.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $this->challengedUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $this->oneTimePasswords->issueTo($user);

        return back()->with('status', __('We sent a code to your email address.'));
    }

    /**
     * Redeem the emailed code and complete the login.
     */
    public function update(VerifyTwoFactorEmailCodeRequest $request): RedirectResponse
    {
        $user = $this->challengedUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        try {
            $this->oneTimePasswords->assertConsumed($user, $request->code());
        } catch (ValidationException $exception) {
            // Mirrors Fortify's own failure path so the lockout counter and any
            // security-alert listeners treat this like any other failed factor.
            $this->events->dispatch(new TwoFactorAuthenticationFailed($user));

            throw $exception;
        }

        $this->events->dispatch(new ValidTwoFactorAuthenticationCodeProvided($user));

        // `login.remember` is consumed here rather than read, so a later request
        // cannot replay the challenge with the same session state.
        $remember = (bool) $request->session()->pull('login.remember', false);
        $request->session()->forget('login.id');

        $this->guard->login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * The account part-way through the challenge, or null when there is none.
     *
     * Requires two-factor authentication to actually be confirmed on the
     * account: without that check, an emailed code would become a second login
     * path for users who never enrolled, which is a weaker door, not a stronger
     * one.
     */
    private function challengedUser(Request $request): ?User
    {
        $id = $request->session()->get('login.id');

        if ($id === null) {
            return null;
        }

        $user = User::query()->find($id);

        if ($user === null || $user->two_factor_confirmed_at === null) {
            return null;
        }

        return $user;
    }
}
