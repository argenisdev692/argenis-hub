<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\UpsertOpportunityHandler;
use Modules\LeadScout\Application\DTOs\UpsertOpportunityData;

/**
 * Opportunity endpoints (spec US-6, plan §5).
 */
final readonly class OpportunityController
{
    public function store(string $uuid, UpsertOpportunityData $data, UpsertOpportunityHandler $upsert): JsonResponse
    {
        $result = $upsert->handleForOutreach($uuid, $data);

        return response()->json([
            'data' => UpsertOpportunityHandler::toData($result['opportunity'], $result['outreach_uuid']),
        ], 201);
    }

    public function update(string $uuid, UpsertOpportunityData $data, UpsertOpportunityHandler $upsert): JsonResponse
    {
        $result = $upsert->handleUpdate($uuid, $data);

        return response()->json([
            'data' => UpsertOpportunityHandler::toData($result['opportunity'], $result['outreach_uuid']),
        ]);
    }
}
