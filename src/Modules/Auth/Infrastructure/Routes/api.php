<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Auth\Infrastructure\Http\Controllers\Api\AuthApiController;

/*
|--------------------------------------------------------------------------
| Auth module — API routes (Sanctum bearer tokens)
|--------------------------------------------------------------------------
|
| Secondary surface, for mobile / external clients only. The web app never
| touches these: it authenticates with sessions + Inertia.
|
| Every route is throttled (FR-04 / OWASP §14). `login` is the only public one
| and carries the tightest limiter, keyed by email+IP so one attacker cannot
| lock out an unrelated account by spending someone else's quota.
|
*/

Route::prefix('auth')->name('api.auth.')->group(function (): void {
    // Deliberately not behind `guest`: this surface is stateless, and that
    // middleware would answer a session-carrying browser with a redirect.
    Route::post('login', [AuthApiController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('login');

    Route::middleware(['auth:sanctum', 'throttle:api-session'])->group(function (): void {
        Route::post('refresh', [AuthApiController::class, 'refresh'])->name('refresh');
        Route::post('logout', [AuthApiController::class, 'logout'])->name('logout');
        Route::get('me', [AuthApiController::class, 'me'])->name('me');
    });
});
