<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\SocialMedia\Infrastructure\Http\Controllers\SocialMediaAiAssistController;
use Modules\SocialMedia\Infrastructure\Http\Controllers\SocialMediaContentController;
use Modules\SocialMedia\Infrastructure\Http\Controllers\SocialMediaContentExportController;

/*
| Social Media module — web (session + Inertia).
|
| Management routes are gated by `permission:*_SOCIAL_MEDIA` (UI authz uses
| permissions, never roles). Static segments (`create`, `bulk-delete`,
| `bulk-restore`, `export`, `ai/*`) are declared BEFORE the `{uuid}` wildcard so
| they are never captured as a UUID. There is no `store` route — content is
| always AI-born (see `ai/generate-content`); `create` only renders the
| 2-step wizard page. AI-assist endpoints carry a tighter throttle — each
| call is a real, billed provider request, and `generate-content` kicks off
| an up-to-5-iteration background job.
*/
Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('social-media')->name('social-media.')->group(function (): void {
    Route::get('/', [SocialMediaContentController::class, 'index'])
        ->middleware('permission:VIEW_ANY_SOCIAL_MEDIA')->name('index');

    Route::get('/create', [SocialMediaContentController::class, 'create'])
        ->middleware('permission:CREATE_SOCIAL_MEDIA')->name('create');

    Route::post('/bulk-delete', [SocialMediaContentController::class, 'bulkDelete'])
        ->middleware('permission:BULK_DELETE_SOCIAL_MEDIA')->name('bulk-delete');

    Route::post('/bulk-restore', [SocialMediaContentController::class, 'bulkRestore'])
        ->middleware('permission:BULK_RESTORE_SOCIAL_MEDIA')->name('bulk-restore');

    Route::get('/export', SocialMediaContentExportController::class)
        ->middleware(['permission:EXPORT_SOCIAL_MEDIA', 'throttle:10,1'])->name('export');

    Route::post('/ai/suggest-topics', [SocialMediaAiAssistController::class, 'suggestTopics'])
        ->middleware(['permission:CREATE_SOCIAL_MEDIA', 'throttle:10,1'])->name('ai.suggest-topics');

    Route::post('/ai/generate-content', [SocialMediaAiAssistController::class, 'generateContent'])
        ->middleware(['permission:CREATE_SOCIAL_MEDIA', 'throttle:5,1'])->name('ai.generate-content');

    Route::get('/ai/{uuid}/status', [SocialMediaAiAssistController::class, 'status'])
        ->middleware(['permission:CREATE_SOCIAL_MEDIA', 'throttle:30,1'])->whereUuid('uuid')->name('ai.status');

    Route::get('/{uuid}/edit', [SocialMediaContentController::class, 'edit'])
        ->middleware('permission:VIEW_SOCIAL_MEDIA')->whereUuid('uuid')->name('edit');

    Route::put('/{uuid}', [SocialMediaContentController::class, 'update'])
        ->middleware('permission:UPDATE_SOCIAL_MEDIA')->whereUuid('uuid')->name('update');

    Route::post('/{uuid}/publish', [SocialMediaContentController::class, 'publish'])
        ->middleware('permission:PUBLISH_SOCIAL_MEDIA')->whereUuid('uuid')->name('publish');

    Route::delete('/{uuid}', [SocialMediaContentController::class, 'destroy'])
        ->middleware('permission:DELETE_SOCIAL_MEDIA')->whereUuid('uuid')->name('destroy');

    Route::post('/{uuid}/restore', [SocialMediaContentController::class, 'restore'])
        ->middleware('permission:RESTORE_SOCIAL_MEDIA')->whereUuid('uuid')->name('restore');
});
