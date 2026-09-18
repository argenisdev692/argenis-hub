<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\GetFunnelMetricsHandler;
use Modules\LeadScout\Application\DTOs\MetricsFilterData;

/**
 * Funnel metrics endpoint (spec US-6, plan §5).
 */
final readonly class MetricsController
{
    public function index(MetricsFilterData $filters, GetFunnelMetricsHandler $metrics): JsonResponse
    {
        return response()->json(['data' => $metrics->handle($filters)]);
    }
}
