<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\VideoEdits\Infrastructure\Http\Controllers\VideoEditController;
use Modules\VideoEdits\Infrastructure\Http\Controllers\VideoEditExportController;
use Modules\VideoEdits\Infrastructure\Http\Controllers\VideoEditReportController;

/*
|--------------------------------------------------------------------------
| Video Edits module — web routes (session + JSON data endpoints)
|--------------------------------------------------------------------------
|
| JSON surface under `/data/admin/video-edits` (spec 001-video-edit, plan §5).
| Every static segment is declared before `/{uuid}`. The Inertia page ships
| with the frontend spec.
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('data/admin/video-edits')
    ->name('video-edits.admin.')
    ->group(function (): void {
        Route::middleware(['permission:VIEW_ANY_VIDEO_EDITS', 'throttle:60,1'])
            ->get('/', [VideoEditController::class, 'index'])->name('index');
        Route::middleware(['permission:CREATE_VIDEO_EDITS', 'throttle:10,1'])
            ->post('/', [VideoEditController::class, 'store'])->name('store');

        // Declared before `/{uuid}` so `export` is never read as an identifier.
        Route::middleware(['permission:EXPORT_VIDEO_EDITS', 'throttle:10,1'])
            ->get('/export', VideoEditExportController::class)->name('export');

        Route::middleware(['permission:DOWNLOAD_VIDEO_EDITS', 'throttle:30,1'])
            ->get('/{uuid}/download-url', [VideoEditController::class, 'downloadUrl'])->whereUuid('uuid')->name('download-url');
        Route::middleware(['permission:CREATE_VIDEO_EDITS', 'throttle:10,1'])
            ->post('/{uuid}/submit', [VideoEditController::class, 'submit'])->whereUuid('uuid')->name('submit');
        Route::middleware(['permission:RETRY_VIDEO_EDITS', 'throttle:10,1'])
            ->post('/{uuid}/retry', [VideoEditController::class, 'retry'])->whereUuid('uuid')->name('retry');

        // V3 AI decision report (US-14), on screen and as a PDF. Declared
        // before `/{uuid}` so the static segment is never read as an id.
        Route::middleware(['permission:VIEW_VIDEO_EDITS', 'throttle:30,1'])
            ->get('/{uuid}/report', VideoEditReportController::class)->whereUuid('uuid')->name('report');

        Route::middleware(['permission:VIEW_VIDEO_EDITS', 'throttle:60,1'])
            ->get('/{uuid}', [VideoEditController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware(['permission:DELETE_VIDEO_EDITS', 'throttle:20,1'])
            ->delete('/{uuid}', [VideoEditController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
    });
