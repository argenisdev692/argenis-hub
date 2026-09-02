<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Invoices\Infrastructure\Http\Controllers\AdminInvoiceController;
use Modules\Invoices\Infrastructure\Http\Controllers\InvoiceExportController;
use Modules\Invoices\Infrastructure\Http\Controllers\InvoicePdfController;

/*
|--------------------------------------------------------------------------
| Invoices module — web routes (session + Inertia + JSON data endpoints)
|--------------------------------------------------------------------------
|
| The billing table lives at `/invoices`; it fetches its own rows from the
| `/data/admin/invoices` JSON surface below. Every static segment
| (`form-options`, `next-number`, `check-number`, `bulk-*`, `export`) is
| declared before `/{uuid}` so those words are never matched as an identifier.
|
*/

Route::middleware(['auth', 'verified', 'permission:VIEW_ANY_INVOICES'])
    ->get('/invoices', [AdminInvoiceController::class, 'page'])
    ->name('invoices.index');

Route::middleware(['auth', 'verified'])
    ->prefix('data/admin/invoices')
    ->name('invoices.admin.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_ANY_INVOICES')->get('/', [AdminInvoiceController::class, 'index'])->name('index');
        Route::middleware('permission:CREATE_INVOICES')->post('/', [AdminInvoiceController::class, 'store'])->name('store');

        Route::middleware('permission:CREATE_INVOICES|UPDATE_INVOICES')
            ->get('/form-options', [AdminInvoiceController::class, 'formOptions'])->name('form-options');
        Route::middleware('permission:CREATE_INVOICES')
            ->get('/next-number', [AdminInvoiceController::class, 'nextNumber'])->name('next-number');
        Route::middleware(['permission:CREATE_INVOICES|UPDATE_INVOICES', 'throttle:30,1'])
            ->get('/check-number', [AdminInvoiceController::class, 'checkNumber'])->name('check-number');

        Route::middleware('permission:BULK_DELETE_INVOICES')->post('/bulk-delete', [AdminInvoiceController::class, 'bulkDelete'])->name('bulk-delete');
        Route::middleware('permission:BULK_RESTORE_INVOICES')->post('/bulk-restore', [AdminInvoiceController::class, 'bulkRestore'])->name('bulk-restore');
        Route::middleware(['permission:EXPORT_INVOICES', 'throttle:10,1'])->get('/export', InvoiceExportController::class)->name('export');

        Route::middleware(['permission:EXPORT_INVOICES', 'throttle:10,1'])
            ->get('/{uuid}/pdf', InvoicePdfController::class)->whereUuid('uuid')->name('pdf');
        Route::middleware('permission:VIEW_INVOICES')->get('/{uuid}', [AdminInvoiceController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware('permission:UPDATE_INVOICES')->put('/{uuid}', [AdminInvoiceController::class, 'update'])->whereUuid('uuid')->name('update');
        Route::middleware('permission:DELETE_INVOICES')->delete('/{uuid}', [AdminInvoiceController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
        Route::middleware('permission:RESTORE_INVOICES')->patch('/{uuid}/restore', [AdminInvoiceController::class, 'restore'])->whereUuid('uuid')->name('restore');
    });
