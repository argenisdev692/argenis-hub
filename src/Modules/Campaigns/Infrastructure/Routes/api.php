<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Campaigns\Infrastructure\Http\Controllers\Api\CampaignApiController;

/*
| Campaigns API routes.
|
| Secondary Sanctum-authenticated surface (mobile/external clients) mirroring
| SocialMedia's `api.php`. `/campaigns/{uuid}` is registered after the AI
| static segments so "ai" is never captured as a UUID (whereUuid rejects it
| anyway, but static-before-wildcard is the project convention regardless).
*/
Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('campaigns')
    ->name('api.campaigns.')
    ->group(function (): void {
        /*
         * Load-bearing, not belt-and-braces with the controller's own
         * `abort_unless`: the controller injects its filter `Data` object so
         * Scramble can document the query parameters, and injection validates
         * during method resolution — before the body runs. Only middleware
         * answers 403 ahead of that; without it an unauthorized caller reads a
         * 422 and learns the filter surface. See
         * `tests/Feature/Api/ApiFilterAuthorizationTest.php`.
         */
        Route::get('/', [CampaignApiController::class, 'index'])
            ->middleware('permission:VIEW_ANY_CAMPAIGNS')->name('index');

        Route::post('/ai/suggest-topics', [CampaignApiController::class, 'suggestTopics'])
            ->middleware('throttle:10,1')->name('ai.suggest-topics');
        Route::post('/ai/generate-campaign', [CampaignApiController::class, 'generateCampaign'])
            ->middleware('throttle:5,1')->name('ai.generate-campaign');

        Route::get('/{uuid}', [CampaignApiController::class, 'show'])
            ->whereUuid('uuid')
            ->name('show');
    });
