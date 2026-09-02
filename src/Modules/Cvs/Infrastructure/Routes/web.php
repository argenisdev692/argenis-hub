<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Cvs\Infrastructure\Http\Controllers\CvController;
use Modules\Cvs\Infrastructure\Http\Controllers\CvExportController;

/*
| Cvs module — web (session + Inertia).
|
| Static segments BEFORE `{uuid}` so bulk/export are never captured as UUIDs.
| Update uses POST + _method spoof for multipart file uploads (Inertia limitation).
*/
Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('cvs')->name('cvs.')->group(function (): void {
    Route::get('/', [CvController::class, 'index'])
        ->middleware('permission:VIEW_ANY_CVS')->name('index');

    Route::post('/', [CvController::class, 'store'])
        ->middleware('permission:CREATE_CVS')->name('store');

    Route::post('/bulk-delete', [CvController::class, 'bulkDelete'])
        ->middleware('permission:BULK_DELETE_CVS')->name('bulk-delete');

    Route::post('/bulk-restore', [CvController::class, 'bulkRestore'])
        ->middleware('permission:BULK_RESTORE_CVS')->name('bulk-restore');

    Route::get('/export', CvExportController::class)
        ->middleware(['permission:EXPORT_CVS', 'throttle:10,1'])->name('export');

    Route::get('/{uuid}', [CvController::class, 'show'])
        ->middleware('permission:VIEW_CVS')->whereUuid('uuid')->name('show');

    Route::put('/{uuid}', [CvController::class, 'update'])
        ->middleware('permission:UPDATE_CVS')->whereUuid('uuid')->name('update');

    Route::post('/{uuid}', [CvController::class, 'update'])
        ->middleware('permission:UPDATE_CVS')->whereUuid('uuid')->name('update.post');

    Route::delete('/{uuid}', [CvController::class, 'destroy'])
        ->middleware('permission:DELETE_CVS')->whereUuid('uuid')->name('destroy');

    Route::patch('/{uuid}/restore', [CvController::class, 'restore'])
        ->middleware('permission:RESTORE_CVS')->whereUuid('uuid')->name('restore');
});
