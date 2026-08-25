<?php

declare(strict_types=1);

namespace Modules\Auth\Infrastructure\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Modules\Auth\Infrastructure\Http\Requests\ResetPasswordWithOtpRequest;
use Modules\Auth\Infrastructure\Http\Requests\SendPasswordResetOtpRequest;
use Modules\Auth\Infrastructure\OneTimePasswords\OneTimePasswordVerifier;

/**
 * Password reset driven by a 6-digit code — never a link or a token URL (FR-11).
 *
 * Owns these two endpoints instead of Fortify because Fortify's reset is built
 * around a signed link, which the spec rules out. Everything downstream of the
 * code check is still Fortify's: applying the new password goes through the
 * `ResetsUserPasswords` contract, so the strength policy, the reuse history and
 * the post-change reactions stay defined in exactly one place.
 *
 * Both endpoints answer identically whether or not the address exists — an
 * enumeration oracle here would undo the point of the whole flow.
 */
final readonly class PasswordResetOtpController
{
    public function __construct(private OneTimePasswordVerifier $oneTimePasswords) {}

    public function store(SendPasswordResetOtpRequest $request): RedirectResponse
    {
        $user = $this->userFor($request->email());

        if ($user !== null) {
            $this->oneTimePasswords->issueTo($user);
        }

        return back()->with('status', __('If that address belongs to an account, a reset code is on its way.'));
    }

    public function update(ResetPasswordWithOtpRequest $request, ResetsUserPasswords $resetter): RedirectResponse
    {
        $user = $this->userFor($request->email());

        if ($user === null) {
            throw ValidationException::withMessages([
                'code' => [__('That code is invalid or has expired. Please request a new one.')],
            ]);
        }

        $this->oneTimePasswords->assertConsumed($user, $request->code());

        $resetter->reset($user, $request->only(['password', 'password_confirmation']));

        return redirect()
            ->route('login')
            ->with('status', __('Your password has been reset. Please sign in.'));
    }

    private function userFor(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }
}
