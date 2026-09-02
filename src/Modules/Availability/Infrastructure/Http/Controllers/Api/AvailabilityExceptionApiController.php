<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Availability\Application\DTOs\AvailabilityExceptionFilterData;
use Modules\Availability\Application\Queries\GetAvailabilityExceptionHandler;
use Modules\Availability\Application\Queries\ListAvailabilityExceptionsHandler;

/**
 * API endpoints for date-specific overrides (closures / forced-open days).
 * Secondary Sanctum-authenticated surface; the primary UI remains Inertia/web.
 * Documented by Scramble via return types + `auth:sanctum` detection — no manual
 * annotations.
 */
final readonly class AvailabilityExceptionApiController
{
    /**
     * List availability exceptions.
     *
     * Returns a paginated list of date exceptions. `per_page` is capped at 100 to
     * bound resource consumption (OWASP API4).
     */
    public function index(Request $request, ListAvailabilityExceptionsHandler $list): JsonResponse
    {
        $filters = AvailabilityExceptionFilterData::validateAndCreate($request);

        return response()->json($list->handle($filters, min(max($request->integer('per_page', 15), 1), 100)));
    }

    /**
     * Show an availability exception.
     *
     * Returns a single date exception by UUID.
     */
    public function show(string $uuid, GetAvailabilityExceptionHandler $get): JsonResponse
    {
        return response()->json(['data' => $get->handle($uuid)]);
    }
}
