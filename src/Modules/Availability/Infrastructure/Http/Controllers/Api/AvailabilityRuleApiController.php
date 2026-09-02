<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Availability\Application\DTOs\AvailabilityRuleFilterData;
use Modules\Availability\Application\Queries\GetAvailabilityRuleHandler;
use Modules\Availability\Application\Queries\ListAvailabilityRulesHandler;

/**
 * API endpoints for the weekly availability template. Secondary
 * Sanctum-authenticated surface; the primary UI remains Inertia/web. Documented
 * by Scramble via return types + `auth:sanctum` detection — no manual annotations.
 */
final readonly class AvailabilityRuleApiController
{
    /**
     * List availability rules.
     *
     * Returns a paginated list of weekly availability rules. `per_page` is capped
     * at 100 to bound resource consumption (OWASP API4).
     */
    public function index(Request $request, ListAvailabilityRulesHandler $list): JsonResponse
    {
        $filters = AvailabilityRuleFilterData::validateAndCreate($request);

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
