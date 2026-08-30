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
        Route::get('/', [PostApiController::class, 'index'])->name('index');

        Route::post('/ai/suggest-topics', [PostApiController::class, 'suggestTopics'])
            ->middleware('throttle:10,1')->name('ai.suggest-topics');
        Route::post('/ai/generate-content', [PostApiController::class, 'generateContent'])
            ->middleware('throttle:10,1')->name('ai.generate-content');
        Route::post('/ai/generate-social-copy', [PostApiController::class, 'generateSocialCopy'])
            ->middleware('throttle:10,1')->name('ai.generate-social-copy');
        Route::post('/ai/generate-reel', [PostApiController::class, 'generateReel'])
            ->middleware('throttle:5,1')->name('ai.generate-reel');

        Route::get('/{uuid}', [PostApiController::class, 'show'])
            ->whereUuid('uuid')
            ->name('show');
    });
