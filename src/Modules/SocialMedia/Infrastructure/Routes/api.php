<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\SocialMedia\Infrastructure\Http\Controllers\Api\PublicSocialMediaController;
use Modules\SocialMedia\Infrastructure\Http\Controllers\Api\SocialMediaApiController;

/*
| Social Media API routes.
|
| `/social-media/public` (list) and `/social-media/public/{uuid}` (detail) are
| the unauthenticated feed of `published` packages — registered BEFORE the
| sanctum group so `public` is never captured as a UUID. `/social-media`
| (sanctum) is the secondary authenticated lookup surface mirroring the web
| CRUD. `/social-media/{uuid}` sits after the AI static segments so "ai" is
| never captured as a UUID (whereUuid rejects it anyway, but
| static-before-wildcard is the project convention regardless).
*/
Route::middleware('throttle:landing-public')->group(function (): void {
    Route::get('/social-media/public', [PublicSocialMediaController::class, 'index'])
        ->name('api.social-media.public');
    Route::get('/social-media/public/{uuid}', [PublicSocialMediaController::class, 'show'])
        ->whereUuid('uuid')
        ->name('api.social-media.public.show');
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('social-media')
    ->name('api.social-media.')
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
        Route::get('/', [SocialMediaApiController::class, 'index'])
            ->middleware('permission:VIEW_ANY_SOCIAL_MEDIA')->name('index');

        Route::post('/ai/suggest-topics', [SocialMediaApiController::class, 'suggestTopics'])
            ->middleware('throttle:10,1')->name('ai.suggest-topics');
        Route::post('/ai/generate-content', [SocialMediaApiController::class, 'generateContent'])
            ->middleware('throttle:5,1')->name('ai.generate-content');

        Route::get('/{uuid}', [SocialMediaApiController::class, 'show'])
            ->whereUuid('uuid')
            ->name('show');
    });
