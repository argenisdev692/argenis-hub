<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Post\Infrastructure\Http\Controllers\PostAiAssistController;
use Modules\Post\Infrastructure\Http\Controllers\PostController;
use Modules\Post\Infrastructure\Http\Controllers\PostExportController;

/*
| Post module — web (session + Inertia).
|
| Management routes are gated by `permission:*_POSTS` (UI authz uses
| permissions, never roles). Static segments (`create`, `bulk-delete`,
| `bulk-restore`, `export`, `ai/*`) are declared BEFORE the `{uuid}` wildcard so
| they are never captured as a UUID. Create/edit are dedicated pages (no modal).
| AI-assist endpoints carry a tighter throttle — each call is a real, billed
| provider request.
*/
Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('posts')->name('posts.')->group(function (): void {
    Route::get('/', [PostController::class, 'index'])
        ->middleware('permission:VIEW_ANY_POSTS')->name('index');

    Route::get('/create', [PostController::class, 'create'])
        ->middleware('permission:CREATE_POSTS')->name('create');

    Route::post('/', [PostController::class, 'store'])
        ->middleware('permission:CREATE_POSTS')->name('store');

    Route::post('/bulk-delete', [PostController::class, 'bulkDelete'])
        ->middleware('permission:BULK_DELETE_POSTS')->name('bulk-delete');

    Route::post('/bulk-restore', [PostController::class, 'bulkRestore'])
        ->middleware('permission:BULK_RESTORE_POSTS')->name('bulk-restore');

    Route::get('/export', PostExportController::class)
        ->middleware(['permission:EXPORT_POSTS', 'throttle:10,1'])->name('export');

    Route::post('/ai/suggest-topics', [PostAiAssistController::class, 'suggestTopics'])
        ->middleware(['permission:CREATE_POSTS', 'throttle:10,1'])->name('ai.suggest-topics');

    Route::post('/ai/generate-content', [PostAiAssistController::class, 'generateContent'])
        ->middleware(['permission:CREATE_POSTS', 'throttle:10,1'])->name('ai.generate-content');

    Route::post('/ai/generate-social-copy', [PostAiAssistController::class, 'generateSocialCopy'])
        ->middleware(['permission:CREATE_POSTS', 'throttle:10,1'])->name('ai.generate-social-copy');

    Route::post('/ai/generate-reel', [PostAiAssistController::class, 'generateReel'])
        ->middleware(['permission:CREATE_POSTS', 'throttle:5,1'])->name('ai.generate-reel');

    Route::get('/{uuid}/edit', [PostController::class, 'edit'])
        ->middleware('permission:VIEW_POSTS')->whereUuid('uuid')->name('edit');

    Route::put('/{uuid}', [PostController::class, 'update'])
        ->middleware('permission:UPDATE_POSTS')->whereUuid('uuid')->name('update');

    Route::delete('/{uuid}', [PostController::class, 'destroy'])
        ->middleware('permission:DELETE_POSTS')->whereUuid('uuid')->name('destroy');

    Route::post('/{uuid}/restore', [PostController::class, 'restore'])
        ->middleware('permission:RESTORE_POSTS')->whereUuid('uuid')->name('restore');
});
