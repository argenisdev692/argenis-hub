<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Authorization\Application\DTOs\PermissionFilterData;
use Modules\Authorization\Application\Queries\GetPermissionHandler;
use Modules\Authorization\Application\Queries\ListPermissionsHandler;

/**
 * API endpoints for permission lookup. Secondary Sanctum-authenticated surface.
 * Responses are documented by Scramble via return types + `auth:sanctum`
 * detection.
 *
 * Filters are documented from the injected {@see PermissionFilterData}: Scramble
 * reads the rules of a `Data` parameter directly, which the equivalent
 * `PermissionFilterData::validateAndCreate($request)` call hides from it — the
 * rules live in a static method the analyser cannot follow, so the endpoint used
 * to document `per_page` and nothing else. Injection validates identically; it
 * is what the web controllers already do.
 */
final readonly class PermissionApiController
{
    /**
     * List permissions.
     *
     * Returns a paginated list of permissions. `per_page` is capped at 100 to
     * bound resource consumption (OWASP API4).
     *
     * `search` matches the permission name.
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    public function index(
        Request $request,
        PermissionFilterData $filters,
        ListPermissionsHandler $list,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_PERMISSIONS'), 403);

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
