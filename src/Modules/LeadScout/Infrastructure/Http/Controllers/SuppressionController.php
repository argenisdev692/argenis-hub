<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\SuppressCompanyHandler;
use Modules\LeadScout\Application\DTOs\SuppressData;

/**
 * Suppression intake (spec FR-17, plan §5).
 */
final readonly class SuppressionController
{
    public function store(SuppressData $data, SuppressCompanyHandler $suppress): JsonResponse
    {
        $row = $suppress->handle($data);

        return response()->json(['data' => [
            'uuid' => $row->uuid,
            'domain' => $row->canonical_domain,
            'source' => $row->source->value,
        ]], 201);
    }
}
