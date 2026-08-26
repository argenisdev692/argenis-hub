<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Company\Infrastructure\Http\Controllers\Api\PublicCompanyController;

/*
|--------------------------------------------------------------------------
| Company module — API routes
|--------------------------------------------------------------------------
|
| One public, unauthenticated GET, consumed by the standalone Astro landing
| sites. The reasoning for leaving it open — and the three controls that stand
| in for authentication — is documented on PublicCompanyController.
|
| Mounted under the api prefix by CompanyServiceProvider so Scramble picks it up
| with the rest of the API surface (config('scramble.api_path')).
|
*/

Route::get('public/company', PublicCompanyController::class)
    ->middleware('throttle:public-company')
    ->name('api.public.company');
