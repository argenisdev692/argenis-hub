<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\UpsertOpportunityHandler;
use Modules\LeadScout\Application\DTOs\OpportunityData;
use Modules\LeadScout\Application\DTOs\UpsertOpportunityData;

/**
 * Opportunity endpoints (spec US-6, plan §5).
 */
final readonly class OpportunityController
{
    public function store(string $uuid, UpsertOpportunityData $data, UpsertOpportunityHandler $upsert): JsonResponse
    {
        return response()->json([
            'data' => OpportunityData::fromEntity($upsert->handleForOutreach($uuid, $data)),
        ], 201);
    }

    public function update(string $uuid, UpsertOpportunityData $data, UpsertOpportunityHandler $upsert): JsonResponse
    {
        return response()->json([
            'data' => OpportunityData::fromEntity($upsert->handleUpdate($uuid, $data)),
        ]);
    }
}
