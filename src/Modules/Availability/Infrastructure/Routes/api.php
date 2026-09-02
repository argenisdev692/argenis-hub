<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Availability\Infrastructure\Http\Controllers\Api\AvailabilityExceptionApiController;
use Modules\Availability\Infrastructure\Http\Controllers\Api\AvailabilityRuleApiController;

/*
| Availability module API routes. Secondary surface for Sanctum-authenticated
| clients. Documented by Scramble under /api/availability-*.
*/
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::prefix('availability-rules')
        ->name('api.availability-rules.')
        ->group(function (): void {
            Route::get('/', [AvailabilityRuleApiController::class, 'index'])
                ->middleware('permission:VIEW_ANY_AVAILABILITY_RULES')->name('index');
            Route::get('/{uuid}', [AvailabilityRuleApiController::class, 'show'])
                ->middleware('permission:VIEW_AVAILABILITY_RULES')
                ->whereUuid('uuid')
                ->name('show');
        });

    Route::prefix('availability-exceptions')
        ->name('api.availability-exceptions.')
        ->group(function (): void {
            Route::get('/', [AvailabilityExceptionApiController::class, 'index'])
                ->middleware('permission:VIEW_ANY_AVAILABILITY_EXCEPTIONS')->name('index');
            Route::get('/{uuid}', [AvailabilityExceptionApiController::class, 'show'])
                ->middleware('permission:VIEW_AVAILABILITY_EXCEPTIONS')
                ->whereUuid('uuid')
                ->name('show');
        });
});
