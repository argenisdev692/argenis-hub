<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Post\Infrastructure\Http\Controllers\Api\PostApiController;
use Modules\Post\Infrastructure\Http\Controllers\Api\PublicPostController;

/*
| Post API routes.
|
| `/posts/public` (list, ?category_uuid=) and `/posts/public/{slug}` (detail)
| are the unauthenticated landing-page feed (only `published` posts) —
| registered BEFORE the sanctum group's `{uuid}` so they are never captured as
| a UUID. `/posts` (sanctum) is the secondary authenticated lookup surface
| mirroring the web CRUD.
*/
Route::middleware('throttle:landing-public')->group(function (): void {
    Route::get('/posts/public', [PublicPostController::class, 'index'])->name('api.posts.public');
    Route::get('/posts/public/{slug}', [PublicPostController::class, 'show'])->name('api.posts.public.show');
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('posts')
    ->name('api.posts.')
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
        Route::get('/', [PostApiController::class, 'index'])
            ->middleware('permission:VIEW_ANY_POSTS')->name('index');

        Route::post('/ai/suggest-topics', [PostApiController::class, 'suggestTopics'])
            ->middleware('throttle:post-ai-assist')->name('ai.suggest-topics');
        // Accepts and queues; answers 202 with the generation row, then the
        // client polls `ai/generations/{uuid}`. Declared before `/{uuid}` so
        // the static `ai` segment is never captured as a UUID.
        Route::post('/ai/generate-content', [PostApiController::class, 'generateContent'])
            ->middleware('throttle:post-ai-generate')->name('ai.generate-content');
        Route::get('/ai/generations/{uuid}', [PostApiController::class, 'generationStatus'])
            ->middleware('throttle:post-ai-status')->whereUuid('uuid')->name('ai.generation-status');
        Route::post('/ai/generate-social-copy', [PostApiController::class, 'generateSocialCopy'])
            ->middleware('throttle:post-ai-assist')->name('ai.generate-social-copy');
        Route::post('/ai/generate-reel', [PostApiController::class, 'generateReel'])
            ->middleware('throttle:post-ai-generate')->name('ai.generate-reel');

        Route::get('/{uuid}', [PostApiController::class, 'show'])
            ->whereUuid('uuid')
            ->name('show');
    });
