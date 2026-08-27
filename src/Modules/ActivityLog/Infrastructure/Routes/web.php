<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\ActivityLog\Infrastructure\Http\Controllers\ActivityLogController;
use Modules\ActivityLog\Infrastructure\Http\Controllers\ActivityLogExportController;

/*
| Activity Log module — web (session + Inertia).
|
| Read-only audit trail: only index / show / export exist. There are NO write
| routes — the trail is immutable and trimmed solely by `activity-log:archive`.
| UI authorization uses permissions (`*_ACTIVITY_LOGS`), never roles.
*/

Route::middleware(['web', 'auth', 'throttle:60,1'])->prefix('activity-logs')->name('activity-logs.')->group(function (): void {
    // Static segments BEFORE the numeric wildcard.
    Route::get('/', [ActivityLogController::class, 'index'])
        ->middleware('permission:VIEW_ANY_ACTIVITY_LOGS')->name('index');

    Route::get('/export', ActivityLogExportController::class)
        ->middleware(['permission:EXPORT_ACTIVITY_LOGS', 'throttle:10,1'])->name('export');

    Route::get('/{activity}', [ActivityLogController::class, 'show'])
        ->middleware('permission:VIEW_ACTIVITY_LOGS')->whereNumber('activity')->name('show');
});
