<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioApplicationController;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioCatalogController;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioCvController;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioPostingController;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioPostingExportController;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioProfileController;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioReferenceController;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioRelationController;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioResolutionController;
use Modules\CvJobStudio\Infrastructure\Http\Controllers\StudioRunController;

/*
| CvJobStudio module — web (session + Inertia).
|
| Static segments BEFORE `{uuid}` so bulk/export/score are never captured as
| UUIDs. Export is throttled to 10/min (generation is expensive — OWASP §14).
*/
Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('cv-studio')->name('cv-studio.')->group(function (): void {
    Route::get('/', [StudioCatalogController::class, 'dashboard'])
        ->middleware('permission:VIEW_ANY_STUDIO_POSTINGS')->name('index');

    Route::get('/profiles', [StudioProfileController::class, 'index'])
        ->middleware('permission:VIEW_ANY_STUDIO_PROFILES')->name('profiles.index');

    Route::post('/profiles', [StudioProfileController::class, 'store'])
        ->middleware('permission:CREATE_STUDIO_PROFILES')->name('profiles.store');

    Route::get('/postings', [StudioPostingController::class, 'index'])
        ->middleware('permission:VIEW_ANY_STUDIO_POSTINGS')->name('postings.index');

    Route::post('/postings', [StudioPostingController::class, 'store'])
        ->middleware('permission:CREATE_STUDIO_POSTINGS')->name('postings.store');

    Route::post('/postings/bulk-delete', [StudioPostingController::class, 'bulkDelete'])
        ->middleware('permission:BULK_DELETE_STUDIO_POSTINGS')->name('postings.bulk-delete');

    Route::post('/postings/bulk-restore', [StudioPostingController::class, 'bulkRestore'])
        ->middleware('permission:BULK_RESTORE_STUDIO_POSTINGS')->name('postings.bulk-restore');

    Route::get('/postings/export', StudioPostingExportController::class)
        ->middleware(['permission:EXPORT_STUDIO_POSTINGS', 'throttle:10,1'])->name('postings.export');

    Route::get('/postings/{uuid}', [StudioPostingController::class, 'show'])
        ->middleware('permission:VIEW_STUDIO_POSTINGS')->whereUuid('uuid')->name('postings.show');

    Route::post('/postings/{uuid}/score', [StudioPostingController::class, 'score'])
        ->middleware('permission:CREATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('postings.score');

    Route::post('/postings/{uuid}/rescore', [StudioPostingController::class, 'rescore'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('postings.rescore');

    Route::delete('/postings/{uuid}', [StudioPostingController::class, 'destroy'])
        ->middleware('permission:DELETE_STUDIO_POSTINGS')->whereUuid('uuid')->name('postings.destroy');

    Route::patch('/postings/{uuid}/restore', [StudioPostingController::class, 'restore'])
        ->middleware('permission:RESTORE_STUDIO_POSTINGS')->whereUuid('uuid')->name('postings.restore');

    Route::put('/postings/{uuid}/status', [StudioApplicationController::class, 'updateStatus'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('postings.status');

    Route::put('/applications/{uuid}/outcome', [StudioApplicationController::class, 'recordOutcome'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('applications.outcome');

    Route::post('/postings/{uuid}/resolve', [StudioResolutionController::class, 'resolve'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('postings.resolve');

    Route::post('/postings/{uuid}/unlink', [StudioResolutionController::class, 'unlink'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('postings.unlink');

    Route::post('/postings/{uuid}/requirements/{ruuid}/dismiss', [StudioPostingController::class, 'dismissRequirement'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('postings.requirements.dismiss');

    Route::get('/references', [StudioReferenceController::class, 'index'])
        ->middleware('permission:VIEW_ANY_STUDIO_POSTINGS')->name('references.index');

    Route::post('/references/{uuid}/paste', [StudioReferenceController::class, 'paste'])
        ->middleware('permission:CREATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('references.paste');

    Route::get('/relations', [StudioRelationController::class, 'index'])
        ->middleware('permission:VIEW_ANY_STUDIO_POSTINGS')->name('relations.index');

    Route::post('/relations/{uuid}/confirm', [StudioRelationController::class, 'confirm'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('relations.confirm');

    Route::post('/relations/{uuid}/reject', [StudioRelationController::class, 'reject'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('relations.reject');

    Route::post('/runs', [StudioRunController::class, 'store'])
        ->middleware('permission:CREATE_STUDIO_POSTINGS')->name('runs.store');

    Route::get('/runs/{uuid}', [StudioRunController::class, 'show'])
        ->middleware('permission:VIEW_STUDIO_POSTINGS')->whereUuid('uuid')->name('runs.show');

    Route::get('/runs/{uuid}/report', [StudioRunController::class, 'report'])
        ->middleware('permission:VIEW_STUDIO_POSTINGS')->whereUuid('uuid')->name('runs.report');

    Route::put('/profiles/{uuid}/policy', [StudioProfileController::class, 'updatePolicy'])
        ->middleware('permission:UPDATE_STUDIO_PROFILES')->whereUuid('uuid')->name('profiles.policy');

    Route::get('/budgets', [StudioCatalogController::class, 'budgets'])
        ->middleware('permission:VIEW_ANY_STUDIO_POSTINGS')->name('budgets.index');

    Route::get('/applications', [StudioApplicationController::class, 'index'])
        ->middleware('permission:VIEW_ANY_STUDIO_POSTINGS')->name('applications.index');

    Route::get('/versions', [StudioCvController::class, 'versions'])
        ->middleware('permission:VIEW_ANY_STUDIO_POSTINGS')->name('versions.index');

    Route::get('/budgets/own-rates', [StudioCatalogController::class, 'ownRates'])
        ->middleware('permission:VIEW_ANY_STUDIO_POSTINGS')->name('budgets.own-rates');

    Route::get('/sources', [StudioCatalogController::class, 'sources'])
        ->middleware('permission:VIEW_ANY_STUDIO_POSTINGS')->name('sources.index');

    Route::post('/cvs/structure', [StudioCvController::class, 'parse'])
        ->middleware('permission:CREATE_STUDIO_POSTINGS')->name('cvs.parse');

    Route::put('/structures/{uuid}', [StudioCvController::class, 'confirm'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('structures.confirm');

    Route::post('/cvs/audit', [StudioCvController::class, 'audit'])
        ->middleware('permission:CREATE_STUDIO_POSTINGS')->name('cvs.audit');

    Route::post('/audits/{uuid}/answers', [StudioCvController::class, 'answers'])
        ->middleware('permission:UPDATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('audits.answers');

    Route::post('/structures/{uuid}/rewrite', [StudioCvController::class, 'rewrite'])
        ->middleware('permission:CREATE_STUDIO_POSTINGS')->whereUuid('uuid')->name('structures.rewrite');

    Route::get('/versions/{uuid}/export', [StudioCvController::class, 'export'])
        ->middleware('permission:EXPORT_STUDIO_POSTINGS')->whereUuid('uuid')->name('versions.export');
});
