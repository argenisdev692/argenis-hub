<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\GetBudgetStatusHandler;
use Modules\LeadScout\Application\Commands\UpdateBudgetsHandler;
use Modules\LeadScout\Application\DTOs\UpdateBudgetsData;

/**
 * Budget month endpoints (spec US-8, plan §5).
 */
final readonly class BudgetController
{
    public function show(GetBudgetStatusHandler $status): JsonResponse
    {
        return response()->json(['data' => $status->handle()]);
    }

    public function update(UpdateBudgetsData $data, UpdateBudgetsHandler $update): JsonResponse
    {
        return response()->json(['data' => $update->handle($data)]);
    }
}
