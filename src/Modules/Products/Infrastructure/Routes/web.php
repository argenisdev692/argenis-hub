<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Products\Infrastructure\Http\Controllers\AdminProductController;

/*
|--------------------------------------------------------------------------
| Products module — web routes (session + Inertia + JSON data endpoints)
|--------------------------------------------------------------------------
|
| The catalog table lives at `/products`; it fetches its own rows from the
| `/data/admin/products` JSON surface below. `bulk-delete` / `bulk-restore`
| and `export` are declared before `/{uuid}` so those words are never matched
| as an identifier (same convention as the Clients module).
|
*/

Route::middleware(['auth', 'verified', 'permission:VIEW_ANY_PRODUCTS'])
    ->get('/products', [AdminProductController::class, 'page'])
    ->name('products.index');

Route::middleware(['auth', 'verified'])
    ->prefix('data/admin/products')
    ->name('products.admin.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_ANY_PRODUCTS')->get('/', [AdminProductController::class, 'index'])->name('index');
        Route::middleware('permission:CREATE_PRODUCTS')->post('/', [AdminProductController::class, 'store'])->name('store');
        Route::middleware('permission:BULK_DELETE_PRODUCTS')->post('/bulk-delete', [AdminProductController::class, 'bulkDelete'])->name('bulk-delete');
        Route::middleware('permission:BULK_RESTORE_PRODUCTS')->post('/bulk-restore', [AdminProductController::class, 'bulkRestore'])->name('bulk-restore');
        Route::middleware(['permission:EXPORT_PRODUCTS', 'throttle:10,1'])->get('/export', [AdminProductController::class, 'export'])->name('export');
        Route::middleware('permission:VIEW_PRODUCTS')->get('/{uuid}', [AdminProductController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware('permission:UPDATE_PRODUCTS')->put('/{uuid}', [AdminProductController::class, 'update'])->whereUuid('uuid')->name('update');
        Route::middleware('permission:DELETE_PRODUCTS')->delete('/{uuid}', [AdminProductController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
        Route::middleware('permission:RESTORE_PRODUCTS')->patch('/{uuid}/restore', [AdminProductController::class, 'restore'])->whereUuid('uuid')->name('restore');
    });
