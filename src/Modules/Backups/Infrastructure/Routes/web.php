<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Backups\Infrastructure\Http\Controllers\AdminBackupController;

/*
|--------------------------------------------------------------------------
| Backups module — web routes (session + Inertia + JSON data endpoints)
|--------------------------------------------------------------------------
|
| The admin panel lives at `/backups`; it fetches its own rows from the
| `/data/admin/backups` JSON surface below. `/export`, `/bulk-delete` and
| `/{uuid}/download` are declared before `/{uuid}` so those static segments are
| never matched as an identifier (same convention as Modules\Services).
|
| Database-only backups: rows are created by the scheduler or the on-demand
| `store()` trigger, never by a form — hence no create/update field routes. A
| deleted archive is physically gone, so there is no restore route either.
|
*/

Route::middleware(['auth', 'verified', 'permission:VIEW_ANY_BACKUPS'])
    ->get('/backups', [AdminBackupController::class, 'page'])
    ->name('backups.index');

Route::middleware(['auth', 'verified'])
    ->prefix('data/admin/backups')
    ->name('backups.admin.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_ANY_BACKUPS')->get('/', [AdminBackupController::class, 'index'])->name('index');
        Route::middleware('permission:CREATE_BACKUPS')->post('/', [AdminBackupController::class, 'store'])->name('store');
        Route::middleware('permission:BULK_DELETE_BACKUPS')->post('/bulk-delete', [AdminBackupController::class, 'bulkDelete'])->name('bulk-delete');
        Route::middleware(['permission:EXPORT_BACKUPS', 'throttle:10,1'])->get('/export', [AdminBackupController::class, 'export'])->name('export');
        Route::middleware('permission:DOWNLOAD_BACKUPS')->get('/{uuid}/download', [AdminBackupController::class, 'download'])->whereUuid('uuid')->name('download');
        Route::middleware('permission:VIEW_BACKUPS')->get('/{uuid}', [AdminBackupController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware('permission:DELETE_BACKUPS')->delete('/{uuid}', [AdminBackupController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
    });
