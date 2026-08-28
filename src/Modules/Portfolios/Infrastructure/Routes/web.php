<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Portfolios\Infrastructure\Http\Controllers\AdminPortfolioController;

/*
|--------------------------------------------------------------------------
| Portfolios module — web routes (session + Inertia + JSON data endpoints)
|--------------------------------------------------------------------------
|
| The admin table lives at `/portfolios`; it fetches its own rows from the
| `/data/admin/portfolios` JSON surface below. `bulk-delete` / `bulk-restore`
| and `export` are declared before `/{uuid}` so those words are never matched
| as an identifier (same convention as the Services module).
|
*/

Route::middleware(['auth', 'verified', 'permission:VIEW_ANY_PORTFOLIOS'])
    ->get('/portfolios', [AdminPortfolioController::class, 'page'])
    ->name('portfolios.index');

Route::middleware(['auth', 'verified'])
    ->prefix('data/admin/portfolios')
    ->name('portfolios.admin.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_ANY_PORTFOLIOS')->get('/', [AdminPortfolioController::class, 'index'])->name('index');
        Route::middleware('permission:CREATE_PORTFOLIOS')->post('/', [AdminPortfolioController::class, 'store'])->name('store');
        Route::middleware('permission:BULK_DELETE_PORTFOLIOS')->post('/bulk-delete', [AdminPortfolioController::class, 'bulkDelete'])->name('bulk-delete');
        Route::middleware('permission:BULK_RESTORE_PORTFOLIOS')->post('/bulk-restore', [AdminPortfolioController::class, 'bulkRestore'])->name('bulk-restore');
        Route::middleware('permission:EXPORT_PORTFOLIOS')->get('/export', [AdminPortfolioController::class, 'export'])->name('export');
        Route::middleware('permission:VIEW_PORTFOLIOS')->get('/{uuid}', [AdminPortfolioController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware('permission:UPDATE_PORTFOLIOS')->put('/{uuid}', [AdminPortfolioController::class, 'update'])->whereUuid('uuid')->name('update');
        Route::middleware('permission:DELETE_PORTFOLIOS')->delete('/{uuid}', [AdminPortfolioController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
        Route::middleware('permission:RESTORE_PORTFOLIOS')->patch('/{uuid}/restore', [AdminPortfolioController::class, 'restore'])->whereUuid('uuid')->name('restore');
    });
