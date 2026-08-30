<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Blog\Infrastructure\Http\Controllers\BlogCategoryController;
use Modules\Blog\Infrastructure\Http\Controllers\BlogCategoryExportController;

/*
| Blog module — web (session + Inertia).
|
| Management routes are gated by `permission:*_BLOG_CATEGORIES` (UI authz uses
| permissions, never roles). Static segments are declared BEFORE the `{uuid}`
| wildcard so `/bulk-delete` and `/bulk-restore` are never captured as a UUID.
*/
Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('blog-categories')->name('blog-categories.')->group(function (): void {
    Route::get('/', [BlogCategoryController::class, 'index'])
        ->middleware('permission:VIEW_ANY_BLOG_CATEGORIES')->name('index');

    Route::post('/', [BlogCategoryController::class, 'store'])
        ->middleware('permission:CREATE_BLOG_CATEGORIES')->name('store');

    Route::post('/bulk-delete', [BlogCategoryController::class, 'bulkDelete'])
        ->middleware('permission:BULK_DELETE_BLOG_CATEGORIES')->name('bulk-delete');

    Route::post('/bulk-restore', [BlogCategoryController::class, 'bulkRestore'])
        ->middleware('permission:BULK_RESTORE_BLOG_CATEGORIES')->name('bulk-restore');

    Route::get('/export', BlogCategoryExportController::class)
        ->middleware(['permission:EXPORT_BLOG_CATEGORIES', 'throttle:10,1'])->name('export');

    Route::get('/{uuid}', [BlogCategoryController::class, 'show'])
        ->middleware('permission:VIEW_BLOG_CATEGORIES')->whereUuid('uuid')->name('show');

    Route::put('/{uuid}', [BlogCategoryController::class, 'update'])
        ->middleware('permission:UPDATE_BLOG_CATEGORIES')->whereUuid('uuid')->name('update');

    Route::delete('/{uuid}', [BlogCategoryController::class, 'destroy'])
        ->middleware('permission:DELETE_BLOG_CATEGORIES')->whereUuid('uuid')->name('destroy');

    Route::post('/{uuid}/restore', [BlogCategoryController::class, 'restore'])
        ->middleware('permission:RESTORE_BLOG_CATEGORIES')->whereUuid('uuid')->name('restore');
});
