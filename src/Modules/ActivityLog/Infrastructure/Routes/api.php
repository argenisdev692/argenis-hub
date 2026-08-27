<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ActivityLog\Infrastructure\Http\Controllers\Api\ActivityLogApiController;

/*
| Activity Log module — API (Sanctum). Secondary surface for mobile clients;
| the web/Inertia routes remain primary. Documented by Scramble (prefix api/*).
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('activity-logs')->name('api.activity-logs.')->group(function (): void {
    Route::get('/', [ActivityLogApiController::class, 'index'])->name('index');
    Route::get('/{id}', [ActivityLogApiController::class, 'show'])->whereNumber('id')->name('show');
});
