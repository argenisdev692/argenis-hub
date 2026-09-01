<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Authorization\Application\Commands\BulkDeletePermissionsHandler;
use Modules\Authorization\Application\Commands\BulkRestorePermissionsHandler;
use Modules\Authorization\Application\Commands\CreatePermissionHandler;
use Modules\Authorization\Application\Commands\DeletePermissionHandler;
use Modules\Authorization\Application\Commands\RestorePermissionHandler;
use Modules\Authorization\Application\Commands\UpdatePermissionHandler;
use Modules\Authorization\Application\DTOs\PermissionData;
use Modules\Authorization\Application\DTOs\PermissionFilterData;
use Modules\Authorization\Application\Queries\GetPermissionHandler;
use Modules\Authorization\Application\Queries\ListPermissionsHandler;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Permission catalogue management. Every route is gated by
 * `permission:*_PERMISSIONS` middleware. Controller stays thin: validate →
 * handler → response.
 */
final readonly class PermissionController
{
    public function index(Request $request, ListPermissionsHandler $list): InertiaResponse|JsonResponse
    {
        $filters = PermissionFilterData::validateAndCreate($request);
        $permissions = $list->handle($filters, min(max($request->integer('per_page', 15), 1), 100));

        return $request->expectsJson()
            ? response()->json($permissions)
            : Inertia::render('permissions/Index', ['permissions' => $permissions, 'filters' => $filters]);
    }

    public function show(string $uuid, GetPermissionHandler $get): InertiaResponse|JsonResponse
    {
        $permission = $get->handle($uuid);

        return request()->expectsJson()
            ? response()->json(['data' => $permission])
            : Inertia::render('permissions/Show', ['permission' => $permission]);
    }

    public function store(PermissionData $data, CreatePermissionHandler $create): RedirectResponse
    {
        (void) $create->handle($data);

        return back()->with('success', __('Permission created.'));
    }

    public function update(string $uuid, PermissionData $data, GetPermissionHandler $get, UpdatePermissionHandler $update): RedirectResponse
    {
        (void) $update->handle($get->handle($uuid), $data);

        return back()->with('success', __('Permission updated.'));
    }

    public function destroy(string $uuid, DeletePermissionHandler $delete): RedirectResponse
    {
        (void) $delete->handle($uuid);

        return back()->with('success', __('Permission suspended.'));
    }

    public function restore(string $uuid, RestorePermissionHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid);

        return back()->with('success', __('Permission restored.'));
    }

    public function bulkDelete(BulkUuidsData $data, BulkDeletePermissionsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count permissions suspended.', ['count' => $count]));
    }

    public function bulkRestore(BulkUuidsData $data, BulkRestorePermissionsHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count permissions restored.', ['count' => $count]));
    }
}
