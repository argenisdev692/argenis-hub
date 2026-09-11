<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Availability\Application\DTOs\AvailabilityRuleFilterData;
use Modules\Availability\Application\Queries\GetAvailabilityRuleHandler;
use Modules\Availability\Application\Queries\ListAvailabilityRulesHandler;

/**
 * API endpoints for the weekly availability template. Secondary
 * Sanctum-authenticated surface; the primary UI remains Inertia/web. Responses
 * are documented by Scramble from the return types.
 *
 * Filters are documented from the injected {@see AvailabilityRuleFilterData}:
 * Scramble reads a `Data` parameter's rules directly, while the equivalent
 * `AvailabilityRuleFilterData::validateAndCreate($request)` call hides them —
 * the rules live in a static method the analyser cannot follow, so this endpoint
 * used to document `per_page` and nothing else. Injection validates identically
 * and is what the web controllers already do.
 */
final readonly class AvailabilityRuleApiController
{
    /**
     * List availability rules.
     *
     * Returns a paginated list of weekly availability rules. `per_page` is capped
     * at 100 to bound resource consumption (OWASP API4).
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    public function index(
        Request $request,
        AvailabilityRuleFilterData $filters,
        ListAvailabilityRulesHandler $list,
    ): JsonResponse {
        return response()->json($list->handle($filters, min(max($request->integer('per_page', 15), 1), 100)));
    }

    /**
     * Show an availability rule.
     *
     * Returns a single weekly availability rule by UUID.
     */
    public function show(string $uuid, GetAvailabilityRuleHandler $get): JsonResponse
    {
        return response()->json(['data' => $get->handle($uuid)]);
    }
}
