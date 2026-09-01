<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Campaigns\Infrastructure\Http\Controllers\CampaignAiAssistController;
use Modules\Campaigns\Infrastructure\Http\Controllers\CampaignController;
use Modules\Campaigns\Infrastructure\Http\Controllers\CampaignExportController;
use Modules\Campaigns\Infrastructure\Http\Controllers\CampaignReportController;

/*
| Campaigns module — web (session + Inertia).
|
| Management routes are gated by `permission:*_CAMPAIGNS` (UI authz uses
| permissions, never roles). Static segments (`create`, `bulk-delete`,
| `bulk-restore`, `export`, `ai/*`) are declared BEFORE the `{uuid}` wildcard
| so they are never captured as a UUID. There is no `store` route — a
| campaign is always AI-born (see `ai/generate-campaign`); `create` only
| renders the 2-step wizard page. AI-assist endpoints carry a tighter
| throttle — each call is a real, billed provider request, and
| `generate-campaign` kicks off an up-to-5-iteration background job.
*/
Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('campaigns')->name('campaigns.')->group(function (): void {
    Route::get('/', [CampaignController::class, 'index'])
        ->middleware('permission:VIEW_ANY_CAMPAIGNS')->name('index');

    Route::get('/create', [CampaignController::class, 'create'])
        ->middleware('permission:CREATE_CAMPAIGNS')->name('create');

    Route::post('/bulk-delete', [CampaignController::class, 'bulkDelete'])
        ->middleware('permission:BULK_DELETE_CAMPAIGNS')->name('bulk-delete');

    Route::post('/bulk-restore', [CampaignController::class, 'bulkRestore'])
        ->middleware('permission:BULK_RESTORE_CAMPAIGNS')->name('bulk-restore');

    Route::get('/export', CampaignExportController::class)
        ->middleware(['permission:EXPORT_CAMPAIGNS', 'throttle:10,1'])->name('export');

    Route::post('/ai/suggest-topics', [CampaignAiAssistController::class, 'suggestTopics'])
        ->middleware(['permission:CREATE_CAMPAIGNS', 'throttle:10,1'])->name('ai.suggest-topics');

    Route::post('/ai/generate-campaign', [CampaignAiAssistController::class, 'generateCampaign'])
        ->middleware(['permission:CREATE_CAMPAIGNS', 'throttle:5,1'])->name('ai.generate-campaign');

    Route::get('/ai/{uuid}/status', [CampaignAiAssistController::class, 'status'])
        ->middleware(['permission:CREATE_CAMPAIGNS', 'throttle:30,1'])->whereUuid('uuid')->name('ai.status');

    Route::get('/{uuid}/edit', [CampaignController::class, 'edit'])
        ->middleware('permission:VIEW_CAMPAIGNS')->whereUuid('uuid')->name('edit');

    Route::get('/{uuid}/report', CampaignReportController::class)
        ->middleware(['permission:VIEW_CAMPAIGNS', 'throttle:20,1'])->whereUuid('uuid')->name('report');

    Route::put('/{uuid}', [CampaignController::class, 'update'])
        ->middleware('permission:UPDATE_CAMPAIGNS')->whereUuid('uuid')->name('update');

    Route::post('/{uuid}/publish', [CampaignController::class, 'publish'])
        ->middleware('permission:PUBLISH_CAMPAIGNS')->whereUuid('uuid')->name('publish');

    Route::delete('/{uuid}', [CampaignController::class, 'destroy'])
        ->middleware('permission:DELETE_CAMPAIGNS')->whereUuid('uuid')->name('destroy');

    Route::post('/{uuid}/restore', [CampaignController::class, 'restore'])
        ->middleware('permission:RESTORE_CAMPAIGNS')->whereUuid('uuid')->name('restore');
});
