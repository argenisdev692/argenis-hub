<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CourseScripts\Infrastructure\Http\Controllers\CourseBibleController;
use Modules\CourseScripts\Infrastructure\Http\Controllers\CourseContentController;
use Modules\CourseScripts\Infrastructure\Http\Controllers\CourseController;
use Modules\CourseScripts\Infrastructure\Http\Controllers\CourseExportController;
use Modules\CourseScripts\Infrastructure\Http\Controllers\DeliverableController;
use Modules\CourseScripts\Infrastructure\Http\Controllers\GenerationRunController;
use Modules\CourseScripts\Infrastructure\Http\Controllers\ScriptVersionController;

/*
|--------------------------------------------------------------------------
| Course Scripts module — web routes (spec 002-course-scripts, plan §5)
|--------------------------------------------------------------------------
|
| Inertia pages and JSON actions in one `course-scripts.` group (the Post
| module convention). Every route carries its own permission; UUID segments
| are constrained; static segments are declared before `/{uuid}`.
|
*/

Route::middleware(['auth', 'verified'])
    ->prefix('course-scripts')
    ->name('course-scripts.')
    ->group(function (): void {
        Route::middleware('permission:VIEW_ANY_COURSE_SCRIPTS')
            ->get('/', [CourseController::class, 'index'])->name('index');
        Route::middleware('permission:CREATE_COURSE_SCRIPTS')
            ->get('/create', [CourseController::class, 'create'])->name('create');
        Route::middleware(['permission:CREATE_COURSE_SCRIPTS', 'throttle:course-scripts-upload'])
            ->post('/', [CourseController::class, 'store'])->name('store');

        // Static segments before `/{uuid}` so they are never read as identifiers.
        Route::middleware(['permission:EXPORT_COURSE_SCRIPTS', 'throttle:course-scripts-export'])
            ->get('/export', CourseExportController::class)->name('export');
        Route::middleware(['permission:DELETE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->post('/bulk-delete', [CourseController::class, 'bulkDelete'])->name('bulk-delete');
        Route::middleware(['permission:RESTORE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->post('/bulk-restore', [CourseController::class, 'bulkRestore'])->name('bulk-restore');

        Route::middleware('permission:VIEW_COURSE_SCRIPTS')
            ->get('/{uuid}', [CourseController::class, 'show'])->whereUuid('uuid')->name('show');
        Route::middleware(['permission:DELETE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->delete('/{uuid}', [CourseController::class, 'destroy'])->whereUuid('uuid')->name('destroy');
        Route::middleware(['permission:RESTORE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->post('/{uuid}/restore', [CourseController::class, 'restore'])->whereUuid('uuid')->name('restore');

        Route::middleware('permission:UPDATE_COURSE_SCRIPTS')
            ->put('/{uuid}/videos/{videoUuid}', [CourseContentController::class, 'updateVideo'])
            ->whereUuid(['uuid', 'videoUuid'])->name('videos.update');
        Route::middleware('permission:UPDATE_COURSE_SCRIPTS')
            ->put('/{uuid}/course-notes', [CourseContentController::class, 'updateCourseNotes'])
            ->whereUuid('uuid')->name('course-notes.update');
        Route::middleware(['permission:UPDATE_COURSE_SCRIPTS', 'throttle:course-scripts-upload'])
            ->post('/{uuid}/contents', [CourseContentController::class, 'attachContent'])
            ->whereUuid('uuid')->name('contents.store');
        Route::middleware(['permission:UPDATE_COURSE_SCRIPTS', 'throttle:course-scripts-upload'])
            ->post('/{uuid}/style-references', [CourseContentController::class, 'attachStyleReference'])
            ->whereUuid('uuid')->name('style-references.store');
        Route::middleware('permission:UPDATE_COURSE_SCRIPTS')
            ->delete('/{uuid}/documents/{documentUuid}', [CourseContentController::class, 'detachDocument'])
            ->whereUuid(['uuid', 'documentUuid'])->name('documents.destroy');

        Route::middleware('permission:UPDATE_COURSE_SCRIPTS')
            ->put('/{uuid}/bible', [CourseBibleController::class, 'update'])
            ->whereUuid('uuid')->name('bible.update');
        Route::middleware(['permission:GENERATE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->post('/{uuid}/prepare', [CourseBibleController::class, 'prepare'])
            ->whereUuid('uuid')->name('prepare');

        // Runs (US-9, US-12, US-13).
        Route::middleware(['permission:GENERATE_COURSE_SCRIPTS', 'throttle:course-scripts-status'])
            ->post('/{uuid}/runs/estimate', [GenerationRunController::class, 'estimate'])
            ->whereUuid('uuid')->name('runs.estimate');
        Route::middleware(['permission:GENERATE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->post('/{uuid}/runs', [GenerationRunController::class, 'start'])
            ->whereUuid('uuid')->name('runs.store');
        Route::middleware(['permission:VIEW_COURSE_SCRIPTS', 'throttle:course-scripts-status'])
            ->get('/runs/{runUuid}', [GenerationRunController::class, 'show'])
            ->whereUuid('runUuid')->name('runs.show');
        Route::middleware(['permission:GENERATE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->post('/runs/{runUuid}/cancel', [GenerationRunController::class, 'cancel'])
            ->whereUuid('runUuid')->name('runs.cancel');
        Route::middleware(['permission:GENERATE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->post('/runs/{runUuid}/retry-failed', [GenerationRunController::class, 'retryFailed'])
            ->whereUuid('runUuid')->name('runs.retry-failed');

        // A video's script (US-5, US-7, US-14).
        Route::middleware('permission:VIEW_COURSE_SCRIPTS')
            ->get('/{uuid}/videos/{videoUuid}/script', [ScriptVersionController::class, 'show'])
            ->whereUuid(['uuid', 'videoUuid'])->name('videos.script');
        Route::middleware('permission:VIEW_COURSE_SCRIPTS')
            ->get('/{uuid}/videos/{videoUuid}/versions', [ScriptVersionController::class, 'versions'])
            ->whereUuid(['uuid', 'videoUuid'])->name('videos.versions');
        Route::middleware(['permission:GENERATE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->post('/{uuid}/videos/{videoUuid}/regenerate', [ScriptVersionController::class, 'regenerate'])
            ->whereUuid(['uuid', 'videoUuid'])->name('videos.regenerate');
        Route::middleware(['permission:GENERATE_COURSE_SCRIPTS', 'throttle:course-scripts-generate'])
            ->post('/{uuid}/videos/{videoUuid}/practice', [ScriptVersionController::class, 'forcePractice'])
            ->whereUuid(['uuid', 'videoUuid'])->name('videos.practice');
        Route::middleware('permission:UPDATE_COURSE_SCRIPTS')
            ->put('/{uuid}/videos/{videoUuid}/versions/{versionUuid}/accept', [ScriptVersionController::class, 'accept'])
            ->whereUuid(['uuid', 'videoUuid', 'versionUuid'])->name('videos.versions.accept');

        // Downloads (US-8).
        Route::middleware(['permission:DOWNLOAD_COURSE_SCRIPTS', 'throttle:course-scripts-download'])
            ->get('/deliverables/{deliverableUuid}/download', [DeliverableController::class, 'download'])
            ->whereUuid('deliverableUuid')->name('deliverables.download');
        Route::middleware(['permission:DOWNLOAD_COURSE_SCRIPTS', 'throttle:course-scripts-download'])
            ->get('/{uuid}/videos/{videoUuid}/bundle', [DeliverableController::class, 'videoBundle'])
            ->whereUuid(['uuid', 'videoUuid'])->name('videos.bundle');
        Route::middleware(['permission:DOWNLOAD_COURSE_SCRIPTS', 'throttle:course-scripts-download'])
            ->get('/{uuid}/bundle', [DeliverableController::class, 'courseBundle'])
            ->whereUuid('uuid')->name('bundle');
    });
