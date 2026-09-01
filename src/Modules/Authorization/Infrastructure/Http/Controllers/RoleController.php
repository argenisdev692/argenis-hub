<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Authorization\Application\Commands\BulkDeleteRolesHandler;
use Modules\Authorization\Application\Commands\BulkRestoreRolesHandler;
use Modules\Authorization\Application\Commands\CreateRoleHandler;
use Modules\Authorization\Application\Commands\DeleteRoleHandler;
use Modules\Authorization\Application\Commands\RestoreRoleHandler;
use Modules\Authorization\Application\Commands\UpdateRoleHandler;
use Modules\Authorization\Application\DTOs\RoleData;
use Modules\Authorization\Application\DTOs\RoleFilterData;
use Modules\Authorization\Application\Queries\GetRoleHandler;
use Modules\Authorization\Application\Queries\ListRolesHandler;
use Modules\Authorization\Domain\Exceptions\ProtectedRoleException;
use Modules\Authorization\Domain\Ports\PermissionRepositoryPort;
use Modules\Authorization\Domain\SystemRoles;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Role management. Every route is gated by `permission:*_ROLES` middleware (UI
 * authz uses permissions, never roles). Controller stays thin: validate → handler
 * → response. The Domain {@see ProtectedRoleException} is translated to a flashed
 * error here so the Domain layer never imports Laravel HTTP.
 */
final readonly class RoleController
{
    public function index(Request $request, ListRolesHandler $list, PermissionRepositoryPort $permissions): InertiaResponse|JsonResponse
    {
        $filters = RoleFilterData::validateAndCreate($request);
        $roles = $list->handle($filters, min(max($request->integer('per_page', 15), 1), 100));

        return $request->expectsJson()
            ? response()->json($roles)
            : Inertia::render('roles/Index', [
                'roles' => $roles,
                'filters' => $filters,
                'availablePermissions' => $permissions->allActiveNames(),
                'protectedRoles' => SystemRoles::PROTECTED,
            ]);
    }

    public function show(string $uuid, GetRoleHandler $get): InertiaResponse|JsonResponse
    {
        $role = $get->handle($uuid);

        return request()->expectsJson()
            ? response()->json(['data' => $role])
            : Inertia::render('roles/Show', ['role' => $role]);
    }

    public function store(RoleData $data, CreateRoleHandler $create): RedirectResponse
    {
        (void) $create->handle($data);

        return back()->with('success', __('Role created.'));
    }

    public function update(string $uuid, RoleData $data, GetRoleHandler $get, UpdateRoleHandler $update): RedirectResponse
    {
        try {
            (void) $update->handle($get->handle($uuid), $data);
        } catch (ProtectedRoleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Role updated.'));
    }

    public function destroy(string $uuid, DeleteRoleHandler $delete): RedirectResponse
    {
        try {
            (void) $delete->handle($uuid);
        } catch (ProtectedRoleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Role suspended.'));
    }

    public function restore(string $uuid, RestoreRoleHandler $restore): RedirectResponse
    {
        (void) $restore->handle($uuid);

        return back()->with('success', __('Role restored.'));
    }

    public function bulkDelete(BulkUuidsData $data, BulkDeleteRolesHandler $handler): RedirectResponse
    {
        try {
            $count = $handler->handle($data);
        } catch (ProtectedRoleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __(':count roles suspended.', ['count' => $count]));
    }

    public function bulkRestore(BulkUuidsData $data, BulkRestoreRolesHandler $handler): RedirectResponse
    {
        $count = $handler->handle($data);

        return back()->with('success', __(':count roles restored.', ['count' => $count]));
    }
}
