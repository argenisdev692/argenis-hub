<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Availability\Infrastructure\Http\Controllers\AvailabilityCalendarController;
use Modules\Availability\Infrastructure\Http\Controllers\AvailabilityExceptionController;
use Modules\Availability\Infrastructure\Http\Controllers\AvailabilityExceptionExportController;
use Modules\Availability\Infrastructure\Http\Controllers\AvailabilityRuleController;
use Modules\Availability\Infrastructure\Http\Controllers\AvailabilityRuleExportController;

/*
| Availability module — web (session + Inertia).
|
| Management routes are gated by `permission:*_AVAILABILITY_RULES` /
| `*_AVAILABILITY_EXCEPTIONS` (UI authz uses permissions, never roles). Static
| segments are declared BEFORE the `{uuid}` wildcard so `/bulk-*` and `/calendar`
| are never captured as a UUID.
*/
Route::middleware(['web', 'auth', 'throttle:60,1'])->group(function (): void {
    Route::prefix('availability-rules')->name('availability-rules.')->group(function (): void {
        Route::get('/', [AvailabilityRuleController::class, 'index'])
            ->middleware('permission:VIEW_ANY_AVAILABILITY_RULES')->name('index');

        Route::post('/', [AvailabilityRuleController::class, 'store'])
            ->middleware('permission:CREATE_AVAILABILITY_RULES')->name('store');

        Route::post('/bulk-delete', [AvailabilityRuleController::class, 'bulkDelete'])
            ->middleware('permission:BULK_DELETE_AVAILABILITY_RULES')->name('bulk-delete');

        Route::post('/bulk-restore', [AvailabilityRuleController::class, 'bulkRestore'])
            ->middleware('permission:BULK_RESTORE_AVAILABILITY_RULES')->name('bulk-restore');

        Route::get('/calendar', AvailabilityCalendarController::class)
            ->middleware('permission:VIEW_ANY_AVAILABILITY_RULES')->name('calendar');

        Route::get('/export', AvailabilityRuleExportController::class)
            ->middleware(['permission:EXPORT_AVAILABILITY_RULES', 'throttle:10,1'])->name('export');

        Route::get('/{uuid}', [AvailabilityRuleController::class, 'show'])
            ->middleware('permission:VIEW_AVAILABILITY_RULES')->whereUuid('uuid')->name('show');

        Route::put('/{uuid}', [AvailabilityRuleController::class, 'update'])
            ->middleware('permission:UPDATE_AVAILABILITY_RULES')->whereUuid('uuid')->name('update');

        Route::delete('/{uuid}', [AvailabilityRuleController::class, 'destroy'])
            ->middleware('permission:DELETE_AVAILABILITY_RULES')->whereUuid('uuid')->name('destroy');

        Route::post('/{uuid}/restore', [AvailabilityRuleController::class, 'restore'])
            ->middleware('permission:RESTORE_AVAILABILITY_RULES')->whereUuid('uuid')->name('restore');
    });

    Route::prefix('availability-exceptions')->name('availability-exceptions.')->group(function (): void {
        Route::get('/', [AvailabilityExceptionController::class, 'index'])
            ->middleware('permission:VIEW_ANY_AVAILABILITY_EXCEPTIONS')->name('index');

        Route::post('/', [AvailabilityExceptionController::class, 'store'])
            ->middleware('permission:CREATE_AVAILABILITY_EXCEPTIONS')->name('store');

        Route::post('/bulk-delete', [AvailabilityExceptionController::class, 'bulkDelete'])
            ->middleware('permission:BULK_DELETE_AVAILABILITY_EXCEPTIONS')->name('bulk-delete');

        Route::post('/bulk-restore', [AvailabilityExceptionController::class, 'bulkRestore'])
            ->middleware('permission:BULK_RESTORE_AVAILABILITY_EXCEPTIONS')->name('bulk-restore');

        Route::get('/export', AvailabilityExceptionExportController::class)
            ->middleware(['permission:EXPORT_AVAILABILITY_EXCEPTIONS', 'throttle:10,1'])->name('export');

        Route::get('/{uuid}', [AvailabilityExceptionController::class, 'show'])
            ->middleware('permission:VIEW_AVAILABILITY_EXCEPTIONS')->whereUuid('uuid')->name('show');

        Route::put('/{uuid}', [AvailabilityExceptionController::class, 'update'])
            ->middleware('permission:UPDATE_AVAILABILITY_EXCEPTIONS')->whereUuid('uuid')->name('update');

        Route::delete('/{uuid}', [AvailabilityExceptionController::class, 'destroy'])
            ->middleware('permission:DELETE_AVAILABILITY_EXCEPTIONS')->whereUuid('uuid')->name('destroy');

        Route::post('/{uuid}/restore', [AvailabilityExceptionController::class, 'restore'])
            ->middleware('permission:RESTORE_AVAILABILITY_EXCEPTIONS')->whereUuid('uuid')->name('restore');
    });
});
