<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Authorization\Infrastructure\Http\Controllers\Api\PermissionApiController;
use Modules\Authorization\Infrastructure\Http\Controllers\Api\RoleApiController;

/*
| Authorization API routes. Secondary surface for Sanctum-authenticated clients.
| Documented by Scramble under /api/roles and /api/permissions.
*/
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::prefix('roles')->name('api.roles.')->group(function (): void {
        Route::get('/', [RoleApiController::class, 'index'])->name('index');
        Route::get('/{uuid}', [RoleApiController::class, 'show'])->whereUuid('uuid')->name('show');
    });

    Route::prefix('permissions')->name('api.permissions.')->group(function (): void {
        Route::get('/', [PermissionApiController::class, 'index'])->name('index');
        Route::get('/{uuid}', [PermissionApiController::class, 'show'])->whereUuid('uuid')->name('show');
    });
});
