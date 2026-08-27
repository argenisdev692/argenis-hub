<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Services\Infrastructure\Http\Controllers\AdminServiceController;

/*
|--------------------------------------------------------------------------
| Services module — web routes (session + Inertia + JSON data endpoints)
|--------------------------------------------------------------------------
|
| The admin table lives at `/services`; it fetches its own rows from the
| `/data/admin/services` JSON surface below. Bulk delete/restore routes are
| declared before `/{uuid}` so "bulk-delete"/"bulk-restore" are never matched
| as an identifier (same convention as Modules\Auth's "revoke-others" route).
|
*/

Route::middleware(['auth', 'verified', 'permission:VIEW_ANY_SERVICES'])
    ->get('/services', [AdminServiceController::class, 'page'])
    ->name('services.index');

Route::middleware(['auth', 'verified'])
    ->prefix('data/admin/services')
    ->name('services.admin.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_ANY_SERVICES')->get('/', [AdminServiceController::class, 'index'])->name('index');
        Route::middleware('permission:CREATE_SERVICES')->post('/', [AdminServiceController::class, 'store'])->name('store');
        Route::middleware('permission:BULK_DELETE_SERVICES')->post('/bulk-delete', [AdminServiceController::class, 'bulkDelete'])->name('bulk-delete');
        Route::middleware('permission:BULK_RESTORE_SERVICES')->post('/bulk-restore', [AdminServiceController::class, 'bulkRestore'])->name('bulk-restore');
        Route::middleware('permission:EXPORT_SERVICES')->get('/export', [AdminServiceController::class, 'export'])->name('export');
        Route::middleware('permission:VIEW_SERVICES')->get('/{uuid}', [AdminServiceController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware('permission:UPDATE_SERVICES')->put('/{uuid}', [AdminServiceController::class, 'update'])->whereUuid('uuid')->name('update');
        Route::middleware('permission:DELETE_SERVICES')->delete('/{uuid}', [AdminServiceController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
        Route::middleware('permission:RESTORE_SERVICES')->patch('/{uuid}/restore', [AdminServiceController::class, 'restore'])->whereUuid('uuid')->name('restore');
    });
