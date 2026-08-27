<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Clients\Infrastructure\Http\Controllers\AdminClientController;

/*
|--------------------------------------------------------------------------
| Clients module — web routes (session + Inertia + JSON data endpoints)
|--------------------------------------------------------------------------
|
| The CRM table lives at `/clients`; it fetches its own rows from the
| `/data/admin/clients` JSON surface below. `bulk-delete` / `bulk-restore`
| and `export` are declared before `/{uuid}` so those words are never matched
| as an identifier (same convention as the Services module).
|
*/

Route::middleware(['auth', 'verified', 'permission:VIEW_ANY_CLIENTS'])
    ->get('/clients', [AdminClientController::class, 'page'])
    ->name('clients.index');

Route::middleware(['auth', 'verified'])
    ->prefix('data/admin/clients')
    ->name('clients.admin.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_ANY_CLIENTS')->get('/', [AdminClientController::class, 'index'])->name('index');
        Route::middleware('permission:CREATE_CLIENTS')->post('/', [AdminClientController::class, 'store'])->name('store');
        Route::middleware('permission:BULK_DELETE_CLIENTS')->post('/bulk-delete', [AdminClientController::class, 'bulkDelete'])->name('bulk-delete');
        Route::middleware('permission:BULK_RESTORE_CLIENTS')->post('/bulk-restore', [AdminClientController::class, 'bulkRestore'])->name('bulk-restore');
        Route::middleware('permission:EXPORT_CLIENTS')->get('/export', [AdminClientController::class, 'export'])->name('export');
        Route::middleware('permission:VIEW_CLIENTS')->get('/{uuid}', [AdminClientController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware('permission:UPDATE_CLIENTS')->put('/{uuid}', [AdminClientController::class, 'update'])->whereUuid('uuid')->name('update');
        Route::middleware('permission:DELETE_CLIENTS')->delete('/{uuid}', [AdminClientController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
        Route::middleware('permission:RESTORE_CLIENTS')->patch('/{uuid}/restore', [AdminClientController::class, 'restore'])->whereUuid('uuid')->name('restore');
    });
