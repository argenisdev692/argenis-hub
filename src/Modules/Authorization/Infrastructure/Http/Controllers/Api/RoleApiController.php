<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Authorization\Application\DTOs\RoleFilterData;
use Modules\Authorization\Application\Queries\GetRoleHandler;
use Modules\Authorization\Application\Queries\ListRolesHandler;

/**
 * API endpoints for role lookup. Secondary Sanctum-authenticated surface; the
 * primary UI stays Inertia/web. Documented by Scramble via return types +
 * `auth:sanctum` detection — no manual annotations.
 */
final readonly class RoleApiController
{
    /**
     * List roles.
     *
     * Returns a paginated list of roles on the `web` guard. `per_page` is capped
     * at 100 to bound resource consumption (OWASP API4).
     */
    public function index(Request $request, ListRolesHandler $list): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_ROLES'), 403);

        $filters = RoleFilterData::validateAndCreate($request);

        return response()->json($list->handle($filters, min(max($request->integer('per_page', 15), 1), 100)));
    }

    /**
     * Show a role.
     *
     * Returns a single role with its permissions by UUID.
     */
    public function show(Request $request, string $uuid, GetRoleHandler $get): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ROLES'), 403);

        return response()->json(['data' => $get->handle($uuid)]);
    }
}
