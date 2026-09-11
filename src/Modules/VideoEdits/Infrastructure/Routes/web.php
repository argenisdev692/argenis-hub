<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\VideoEdits\Infrastructure\Http\Controllers\VideoEditController;

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
        Route::middleware('permission:VIEW_ANY_VIDEO_EDITS')
            ->get('/', [VideoEditController::class, 'index'])->name('index');
        Route::middleware(['permission:CREATE_VIDEO_EDITS', 'throttle:10,1'])
            ->post('/', [VideoEditController::class, 'store'])->name('store');

        Route::middleware(['permission:DOWNLOAD_VIDEO_EDITS', 'throttle:30,1'])
            ->get('/{uuid}/download-url', [VideoEditController::class, 'downloadUrl'])->whereUuid('uuid')->name('download-url');
        Route::middleware(['permission:CREATE_VIDEO_EDITS', 'throttle:10,1'])
            ->post('/{uuid}/submit', [VideoEditController::class, 'submit'])->whereUuid('uuid')->name('submit');
        Route::middleware(['permission:RETRY_VIDEO_EDITS', 'throttle:10,1'])
            ->post('/{uuid}/retry', [VideoEditController::class, 'retry'])->whereUuid('uuid')->name('retry');

        Route::middleware('permission:VIEW_VIDEO_EDITS')
            ->get('/{uuid}', [VideoEditController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware(['permission:DELETE_VIDEO_EDITS', 'throttle:20,1'])
            ->delete('/{uuid}', [VideoEditController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
    });
