<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ActivityLog\Infrastructure\Http\Controllers\Api\ActivityLogApiController;

/*
| Activity Log module — API (Sanctum). Secondary surface for mobile clients;
| the web/Inertia routes remain primary. Documented by Scramble (prefix api/*).
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('activity-logs')->name('api.activity-logs.')->group(function (): void {
    /*
     * Load-bearing, not belt-and-braces with the controller's own
     * `abort_unless`: the controller injects `ActivityLogFilterData`, and
     * injection validates during method resolution — before the body runs. Only
     * middleware answers 403 ahead of that; without it an unauthorized caller
     * reads a 422 and learns the filter surface. See
     * `tests/Feature/Api/ApiFilterAuthorizationTest.php`.
     */
    Route::get('/', [ActivityLogApiController::class, 'index'])
        ->middleware('permission:VIEW_ANY_ACTIVITY_LOGS')->name('index');
    Route::get('/{id}', [ActivityLogApiController::class, 'show'])->whereNumber('id')->name('show');
});
