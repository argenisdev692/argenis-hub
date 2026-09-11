<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Authorization\Application\DTOs\RoleFilterData;
use Modules\Authorization\Application\Queries\GetRoleHandler;
use Modules\Authorization\Application\Queries\ListRolesHandler;

/**
 * API endpoints for role lookup. Secondary Sanctum-authenticated surface; the
 * primary UI stays Inertia/web. Responses are documented by Scramble from the
 * return types.
 *
 * Filters are documented from the injected {@see RoleFilterData}: Scramble reads
 * a `Data` parameter's rules directly, while the equivalent
 * `RoleFilterData::validateAndCreate($request)` call hides them — the rules live
 * in a static method the analyser cannot follow, so this endpoint used to
 * document `per_page` and nothing else. Injection validates identically and is
 * what the web controllers already do.
 */
final readonly class RoleApiController
{
    /**
     * List roles.
     *
     * Returns a paginated list of roles on the `web` guard. `per_page` is capped
     * at 100 to bound resource consumption (OWASP API4).
     *
     * `search` matches the role name.
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    public function index(
        Request $request,
        RoleFilterData $filters,
        ListRolesHandler $list,
    ): JsonResponse {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_ROLES'), 403);

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
