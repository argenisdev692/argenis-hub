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
        /*
         * Load-bearing, not belt-and-braces with the controller's own
         * `abort_unless`: the controller injects its filter `Data` object so
         * Scramble can document the query parameters, and injection validates
         * during method resolution — before the body runs. Only middleware
         * answers 403 ahead of that; without it an unauthorized caller reads a
         * 422 and learns the filter surface. See
         * `tests/Feature/Api/ApiFilterAuthorizationTest.php`.
         */
        Route::get('/', [BlogCategoryApiController::class, 'index'])
            ->middleware('permission:VIEW_ANY_BLOG_CATEGORIES')->name('index');
        Route::get('/{uuid}', [BlogCategoryApiController::class, 'show'])
            ->whereUuid('uuid')
            ->name('show');
    });
