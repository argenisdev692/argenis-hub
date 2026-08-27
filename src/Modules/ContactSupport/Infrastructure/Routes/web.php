<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ContactSupport\Infrastructure\Http\Controllers\AdminContactSupportController;

/*
|--------------------------------------------------------------------------
| Contact Support module — web routes (session + Inertia + JSON data)
|--------------------------------------------------------------------------
|
| The support inbox lives at `/contact-supports`; it fetches its own rows
| from the `/data/admin/contact-supports` JSON surface below. `bulk-delete`
| / `bulk-restore` and `export` are declared before `/{uuid}` so those words
| are never matched as an identifier (same convention as the Services module).
|
*/

Route::middleware(['auth', 'verified', 'permission:VIEW_ANY_CONTACT_SUPPORTS'])
    ->get('/contact-supports', [AdminContactSupportController::class, 'page'])
    ->name('contact-supports.index');

Route::middleware(['auth', 'verified'])
    ->prefix('data/admin/contact-supports')
    ->name('contact-supports.admin.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_ANY_CONTACT_SUPPORTS')->get('/', [AdminContactSupportController::class, 'index'])->name('index');
        Route::middleware('permission:CREATE_CONTACT_SUPPORTS')->post('/', [AdminContactSupportController::class, 'store'])->name('store');
        Route::middleware('permission:BULK_DELETE_CONTACT_SUPPORTS')->post('/bulk-delete', [AdminContactSupportController::class, 'bulkDelete'])->name('bulk-delete');
        Route::middleware('permission:BULK_RESTORE_CONTACT_SUPPORTS')->post('/bulk-restore', [AdminContactSupportController::class, 'bulkRestore'])->name('bulk-restore');
        Route::middleware('permission:EXPORT_CONTACT_SUPPORTS')->get('/export', [AdminContactSupportController::class, 'export'])->name('export');
        Route::middleware('permission:VIEW_CONTACT_SUPPORTS')->get('/{uuid}', [AdminContactSupportController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware('permission:UPDATE_CONTACT_SUPPORTS')->put('/{uuid}', [AdminContactSupportController::class, 'update'])->whereUuid('uuid')->name('update');
        Route::middleware('permission:DELETE_CONTACT_SUPPORTS')->delete('/{uuid}', [AdminContactSupportController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
        Route::middleware('permission:RESTORE_CONTACT_SUPPORTS')->patch('/{uuid}/restore', [AdminContactSupportController::class, 'restore'])->whereUuid('uuid')->name('restore');
    });
