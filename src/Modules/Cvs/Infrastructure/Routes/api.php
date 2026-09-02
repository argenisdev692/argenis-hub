<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Cvs\Infrastructure\Http\Controllers\Api\CvApiController;

/*
| Cvs API — Sanctum secondary surface (read lookup for Scramble / mobile).
| Writes stay on the Inertia web controller.
*/
Route::middleware(['auth:sanctum', 'throttle:60,1'])
    ->prefix('cvs')
    ->name('api.cvs.')
    ->group(function (): void {
        Route::get('/', [CvApiController::class, 'index'])
            ->middleware('permission:VIEW_ANY_CVS')
            ->name('index');
        Route::get('/{uuid}', [CvApiController::class, 'show'])
            ->middleware('permission:VIEW_CVS')
            ->whereUuid('uuid')
            ->name('show');
    });
