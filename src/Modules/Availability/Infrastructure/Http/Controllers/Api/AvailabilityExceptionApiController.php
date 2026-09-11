<?php

declare(strict_types=1);

namespace Modules\Availability\Infrastructure\Http\Controllers\Api;

use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Availability\Application\DTOs\AvailabilityExceptionFilterData;
use Modules\Availability\Application\Queries\GetAvailabilityExceptionHandler;
use Modules\Availability\Application\Queries\ListAvailabilityExceptionsHandler;

/**
 * API endpoints for date-specific overrides (closures / forced-open days).
 * Secondary Sanctum-authenticated surface; the primary UI remains Inertia/web.
 * Responses are documented by Scramble from the return types.
 *
 * Filters are documented from the injected {@see AvailabilityExceptionFilterData}: Scramble reads a
 * `Data` parameter's rules directly, while the equivalent
 * `AvailabilityExceptionFilterData::validateAndCreate($request)` call hides them — the
 * rules live in a static method the analyser cannot follow, so this endpoint
 * used to document `per_page` and nothing else. Injection validates
 * identically and is what the web controllers already do.
 */
final readonly class AvailabilityExceptionApiController
{
    /**
     * List availability exceptions.
     *
     * Returns a paginated list of date exceptions. `per_page` is capped at 100 to
     * bound resource consumption (OWASP API4).
     */
    #[QueryParameter(
        'per_page',
        description: 'Rows per page, clamped to 1–100.',
        type: 'int',
        default: 15,
    )]
    public function index(
        Request $request,
        AvailabilityExceptionFilterData $filters,
        ListAvailabilityExceptionsHandler $list,
    ): JsonResponse {
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
