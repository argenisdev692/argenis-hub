<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Authorization\Application\DTOs\PermissionFilterData;
use Modules\Authorization\Application\Queries\GetPermissionHandler;
use Modules\Authorization\Application\Queries\ListPermissionsHandler;

/**
 * API endpoints for permission lookup. Secondary Sanctum-authenticated surface.
 * Documented by Scramble via return types + `auth:sanctum` detection — no manual
 * annotations.
 */
final readonly class PermissionApiController
{
    /**
     * List permissions.
     *
     * Returns a paginated list of permissions. `per_page` is capped at 100 to
     * bound resource consumption (OWASP API4).
     */
    public function index(Request $request, ListPermissionsHandler $list): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_PERMISSIONS'), 403);

        $filters = PermissionFilterData::validateAndCreate($request);

        return response()->json($list->handle($filters, min(max($request->integer('per_page', 15), 1), 100)));
    }

    /**
     * Show a permission.
     *
     * Returns a single permission by UUID.
     */
    public function show(Request $request, string $uuid, GetPermissionHandler $get): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_PERMISSIONS'), 403);

        return response()->json(['data' => $get->handle($uuid)]);
    }
}
