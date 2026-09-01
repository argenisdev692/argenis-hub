<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Authorization\Infrastructure\Http\Controllers\PermissionController;
use Modules\Authorization\Infrastructure\Http\Controllers\PermissionExportController;
use Modules\Authorization\Infrastructure\Http\Controllers\RoleController;
use Modules\Authorization\Infrastructure\Http\Controllers\RoleExportController;

/*
| Authorization module — web (session + Inertia).
|
| Gated by `permission:*_ROLES` / `permission:*_PERMISSIONS` (UI authz uses
| permissions, never roles). Static segments (`/bulk-*`, `/export`) are declared
| BEFORE the `{uuid}` wildcard so they are never captured as a UUID.
*/
Route::middleware(['web', 'auth', 'throttle:60,1'])->group(function (): void {
    Route::prefix('roles')->name('roles.')->group(function (): void {
        Route::get('/', [RoleController::class, 'index'])
            ->middleware('permission:VIEW_ANY_ROLES')->name('index');

        Route::post('/', [RoleController::class, 'store'])
            ->middleware('permission:CREATE_ROLES')->name('store');

        Route::post('/bulk-delete', [RoleController::class, 'bulkDelete'])
            ->middleware('permission:BULK_DELETE_ROLES')->name('bulk-delete');

        Route::post('/bulk-restore', [RoleController::class, 'bulkRestore'])
            ->middleware('permission:BULK_RESTORE_ROLES')->name('bulk-restore');

        Route::get('/export', RoleExportController::class)
            ->middleware(['permission:EXPORT_ROLES', 'throttle:10,1'])->name('export');

        Route::get('/{uuid}', [RoleController::class, 'show'])
            ->middleware('permission:VIEW_ROLES')->whereUuid('uuid')->name('show');

        Route::put('/{uuid}', [RoleController::class, 'update'])
            ->middleware('permission:UPDATE_ROLES')->whereUuid('uuid')->name('update');

        Route::delete('/{uuid}', [RoleController::class, 'destroy'])
            ->middleware('permission:DELETE_ROLES')->whereUuid('uuid')->name('destroy');

        Route::post('/{uuid}/restore', [RoleController::class, 'restore'])
            ->middleware('permission:RESTORE_ROLES')->whereUuid('uuid')->name('restore');
    });

    Route::prefix('permissions')->name('permissions.')->group(function (): void {
        Route::get('/', [PermissionController::class, 'index'])
            ->middleware('permission:VIEW_ANY_PERMISSIONS')->name('index');

        Route::post('/', [PermissionController::class, 'store'])
            ->middleware('permission:CREATE_PERMISSIONS')->name('store');

        Route::post('/bulk-delete', [PermissionController::class, 'bulkDelete'])
            ->middleware('permission:BULK_DELETE_PERMISSIONS')->name('bulk-delete');

        Route::post('/bulk-restore', [PermissionController::class, 'bulkRestore'])
            ->middleware('permission:BULK_RESTORE_PERMISSIONS')->name('bulk-restore');

        Route::get('/export', PermissionExportController::class)
            ->middleware(['permission:EXPORT_PERMISSIONS', 'throttle:10,1'])->name('export');

        Route::get('/{uuid}', [PermissionController::class, 'show'])
            ->middleware('permission:VIEW_PERMISSIONS')->whereUuid('uuid')->name('show');

        Route::put('/{uuid}', [PermissionController::class, 'update'])
            ->middleware('permission:UPDATE_PERMISSIONS')->whereUuid('uuid')->name('update');

        Route::delete('/{uuid}', [PermissionController::class, 'destroy'])
            ->middleware('permission:DELETE_PERMISSIONS')->whereUuid('uuid')->name('destroy');

        Route::post('/{uuid}/restore', [PermissionController::class, 'restore'])
            ->middleware('permission:RESTORE_PERMISSIONS')->whereUuid('uuid')->name('restore');
    });
});
