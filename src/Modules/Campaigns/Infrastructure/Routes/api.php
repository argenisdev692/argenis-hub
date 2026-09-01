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
        Route::get('/', [CampaignApiController::class, 'index'])->name('index');

        Route::post('/ai/suggest-topics', [CampaignApiController::class, 'suggestTopics'])
            ->middleware('throttle:10,1')->name('ai.suggest-topics');
        Route::post('/ai/generate-campaign', [CampaignApiController::class, 'generateCampaign'])
            ->middleware('throttle:5,1')->name('ai.generate-campaign');

        Route::get('/{uuid}', [CampaignApiController::class, 'show'])
            ->whereUuid('uuid')
            ->name('show');
    });
