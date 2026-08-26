<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Services\Infrastructure\Http\Controllers\Api\PublicServiceController;

/*
|--------------------------------------------------------------------------
| Services module — API routes
|--------------------------------------------------------------------------
|
| One public, unauthenticated GET, consumed by the landing page `<select>`
| and any standalone site. Reasoning documented on PublicServiceController.
|
| Mounted under the api prefix by ServicesServiceProvider so Scramble picks
| it up with the rest of the API surface (config('scramble.api_path')).
|
*/

Route::get('public/services', PublicServiceController::class)
    ->middleware('throttle:public-services')
    ->name('api.public.services');
