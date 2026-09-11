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
    /*
     * `permission:*` on the index routes is load-bearing, not belt-and-braces
     * with the controller's own `abort_unless`. The controllers inject their
     * filter `Data` object (so Scramble can document the query parameters), and
     * injection validates during method resolution — before the body runs. Only
     * middleware gets to answer 403 ahead of that; without it an unauthorized
     * caller reads a 422 and learns the filter surface. See
     * `tests/Feature/Api/ApiFilterAuthorizationTest.php`.
     */
    Route::prefix('roles')->name('api.roles.')->group(function (): void {
        Route::get('/', [RoleApiController::class, 'index'])
            ->middleware('permission:VIEW_ANY_ROLES')->name('index');
        Route::get('/{uuid}', [RoleApiController::class, 'show'])->whereUuid('uuid')->name('show');
    });

    Route::prefix('permissions')->name('api.permissions.')->group(function (): void {
        Route::get('/', [PermissionApiController::class, 'index'])
            ->middleware('permission:VIEW_ANY_PERMISSIONS')->name('index');
        Route::get('/{uuid}', [PermissionApiController::class, 'show'])->whereUuid('uuid')->name('show');
    });
});
