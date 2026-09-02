<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\PaymentAccounts\Infrastructure\Http\Controllers\AdminPaymentAccountController;

/*
|--------------------------------------------------------------------------
| PaymentAccounts module — web routes (session + Inertia + JSON data endpoints)
|--------------------------------------------------------------------------
|
| The rails table lives at `/payment-accounts`; it fetches its own rows from
| the `/data/admin/payment-accounts` JSON surface below. `bulk-delete` /
| `bulk-restore` and `export` are declared before `/{uuid}` so those words are
| never matched as an identifier (same convention as the Clients module).
|
*/

Route::middleware(['auth', 'verified', 'permission:VIEW_ANY_PAYMENT_ACCOUNTS'])
    ->get('/payment-accounts', [AdminPaymentAccountController::class, 'page'])
    ->name('payment-accounts.index');

Route::middleware(['auth', 'verified'])
    ->prefix('data/admin/payment-accounts')
    ->name('payment-accounts.admin.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_ANY_PAYMENT_ACCOUNTS')->get('/', [AdminPaymentAccountController::class, 'index'])->name('index');
        Route::middleware('permission:CREATE_PAYMENT_ACCOUNTS')->post('/', [AdminPaymentAccountController::class, 'store'])->name('store');
        Route::middleware('permission:BULK_DELETE_PAYMENT_ACCOUNTS')->post('/bulk-delete', [AdminPaymentAccountController::class, 'bulkDelete'])->name('bulk-delete');
        Route::middleware('permission:BULK_RESTORE_PAYMENT_ACCOUNTS')->post('/bulk-restore', [AdminPaymentAccountController::class, 'bulkRestore'])->name('bulk-restore');
        Route::middleware(['permission:EXPORT_PAYMENT_ACCOUNTS', 'throttle:10,1'])->get('/export', [AdminPaymentAccountController::class, 'export'])->name('export');
        Route::middleware('permission:VIEW_PAYMENT_ACCOUNTS')->get('/{uuid}', [AdminPaymentAccountController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware('permission:UPDATE_PAYMENT_ACCOUNTS')->put('/{uuid}', [AdminPaymentAccountController::class, 'update'])->whereUuid('uuid')->name('update');
        Route::middleware('permission:DELETE_PAYMENT_ACCOUNTS')->delete('/{uuid}', [AdminPaymentAccountController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
        Route::middleware('permission:RESTORE_PAYMENT_ACCOUNTS')->patch('/{uuid}/restore', [AdminPaymentAccountController::class, 'restore'])->whereUuid('uuid')->name('restore');
    });
