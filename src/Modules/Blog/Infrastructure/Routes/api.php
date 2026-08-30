<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Blog\Infrastructure\Http\Controllers\Api\BlogCategoryApiController;
use Modules\Blog\Infrastructure\Http\Controllers\Api\PublicBlogCategoryController;

/*
| Blog category API routes.
|
| `/blog-categories/public` is the unauthenticated landing-page feed (every
| active category + published post count) — registered BEFORE the sanctum
| group's `{uuid}` so it is never captured as a UUID. `/blog-categories`
| (sanctum) is the secondary authenticated lookup surface.
*/
Route::get('/blog-categories/public', [PublicBlogCategoryController::class, 'index'])
    ->middleware('throttle:landing-public')
    ->name('api.blog-categories.public');

Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('blog-categories')
    ->name('api.blog-categories.')
    ->group(function (): void {
        Route::get('/', [BlogCategoryApiController::class, 'index'])->name('index');
        Route::get('/{uuid}', [BlogCategoryApiController::class, 'show'])
            ->whereUuid('uuid')
            ->name('show');
    });
