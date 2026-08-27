<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ContactSupport\Infrastructure\Http\Controllers\Api\PublicContactSupportController;
use Spatie\Honeypot\ProtectAgainstSpam;

/*
|--------------------------------------------------------------------------
| Contact Support module — API routes
|--------------------------------------------------------------------------
|
| One public, unauthenticated POST, consumed by the landing-page contact
| form and any standalone site. Throttled per IP and honeypot-protected;
| reasoning documented on PublicContactSupportController.
|
| Mounted under the `api` prefix by ContactSupportServiceProvider so Scramble
| picks it up with the rest of the API surface (config('scramble.api_path')).
|
*/

Route::post('public/contact-supports', PublicContactSupportController::class)
    ->middleware(['throttle:public-contact-supports', ProtectAgainstSpam::class])
    ->name('api.public.contact-supports');
