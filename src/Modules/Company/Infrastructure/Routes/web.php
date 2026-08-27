<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Company\Infrastructure\Http\Controllers\CompanyController;

/*
|--------------------------------------------------------------------------
| Company module — web routes (session + Inertia)
|--------------------------------------------------------------------------
|
| A singleton, so there is no index and no {uuid} segment. There is no store —
| the row is provisioned by CompanySeeder. It does support a reversible soft
| delete: DELETE trashes the record, PATCH /restore brings it back.
|
| VIEW_COMPANY_DATA guards reading, UPDATE_COMPANY_DATA guards the writes,
| DELETE_COMPANY_DATA / RESTORE_COMPANY_DATA guard the delete pair (SUPER_ADMIN
| only). The logo upload carries the tightest limiter: every accepted file is
| decoded and re-encoded, so it costs real CPU where a field edit costs a query.
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('settings/company')
    ->name('company.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_COMPANY_DATA')->group(function (): void {
            Route::get('/', [CompanyController::class, 'show'])->name('show');
            Route::get('/edit', [CompanyController::class, 'edit'])->name('edit');
        });

        Route::middleware('permission:UPDATE_COMPANY_DATA')->group(function (): void {
            Route::put('/', [CompanyController::class, 'update'])
                ->middleware('throttle:6,1')
                ->name('update');

            Route::post('/logos', [CompanyController::class, 'updateLogos'])
                ->middleware('throttle:company-logos')
                ->name('logos.update');
        });

        Route::middleware('permission:DELETE_COMPANY_DATA')
            ->delete('/', [CompanyController::class, 'destroy'])
            ->middleware('throttle:6,1')
            ->name('destroy');

        Route::middleware('permission:RESTORE_COMPANY_DATA')
            ->patch('/restore', [CompanyController::class, 'restore'])
            ->middleware('throttle:6,1')
            ->name('restore');
    });
