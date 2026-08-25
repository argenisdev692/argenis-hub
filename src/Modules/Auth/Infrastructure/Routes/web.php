<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Infrastructure\Http\Controllers\AuthSessionController;
use Modules\Auth\Infrastructure\Http\Controllers\EmailVerificationOtpController;
use Modules\Auth\Infrastructure\Http\Controllers\PasswordResetOtpController;
use Modules\Auth\Infrastructure\Http\Controllers\TrustedDeviceController;
use Modules\Auth\Infrastructure\Http\Controllers\TwoFactorEmailCodeController;

/*
|--------------------------------------------------------------------------
| Auth module — web routes (Inertia + session)
|--------------------------------------------------------------------------
|
| Only what Fortify does not already own. Login, logout, registration, the 2FA
| endpoints and the email-verification notification stay on Fortify's routes and
| are customised through its extension points instead of being re-declared here.
|
| Every route below is throttled (FR-04) and every authenticated route is
| additionally guarded by a permission (OWASP §1); ownership is re-checked
| inside the handlers (OWASP §11).
|
*/

// Password reset by 6-digit code — guests only, never a link (FR-11).
Route::middleware('guest')->group(function (): void {
    Route::post('password/reset-code', [PasswordResetOtpController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('auth.password.reset-code');

    Route::post('password/reset-with-code', [PasswordResetOtpController::class, 'update'])
        ->middleware('throttle:password-reset')
        ->name('auth.password.reset-with-code');
});

// Emailed 6-digit code as an alternative second factor at the login challenge.
// Guests only: the first factor is already cleared, but the session is not yet
// authenticated, and `login.id` is what authorises both endpoints.
Route::middleware('guest')->group(function (): void {
    Route::post('two-factor-challenge/email-code', [TwoFactorEmailCodeController::class, 'store'])
        ->middleware('throttle:two-factor-email-send')
        ->name('auth.two-factor.email-code.send');

    Route::post('two-factor-challenge/email-code/verify', [TwoFactorEmailCodeController::class, 'update'])
        ->middleware('throttle:two-factor-email-verify')
        ->name('auth.two-factor.email-code.verify');
});

// Email verification by 6-digit code — signed in but not yet verified (FR-02).
Route::middleware('auth')->group(function (): void {
    Route::post('email/verify-code', [EmailVerificationOtpController::class, 'store'])
        ->middleware('throttle:otp-verify')
        ->name('auth.email.verify-code');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('two-factor/trusted-device', [TrustedDeviceController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('auth.two-factor.trusted-device');

    Route::prefix('settings/sessions')->group(function (): void {
        Route::get('/', [AuthSessionController::class, 'index'])
            ->middleware('permission:VIEW_ANY_AUTH_SESSIONS')
            ->name('auth.sessions.index');

        // Declared before the {uuid} route so "revoke-others" is never matched as an identifier.
        Route::post('revoke-others', [AuthSessionController::class, 'destroyOthers'])
            ->middleware('permission:BULK_DELETE_AUTH_SESSIONS')
            ->name('auth.sessions.revoke-others');

        Route::delete('{uuid}', [AuthSessionController::class, 'destroy'])
            ->middleware('permission:DELETE_AUTH_SESSIONS')
            ->whereUuid('uuid')
            ->name('auth.sessions.destroy');
    });
});
