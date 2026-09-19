<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\LeadScout\Application\Commands\CreateManualLeadHandler;
use Modules\LeadScout\Application\DTOs\CompanyData;
use Modules\LeadScout\Application\DTOs\CreateManualLeadData;

/**
 * Manual lead intake (spec FR-20, plan §5).
 */
final readonly class ManualLeadController
{
    public function store(Request $request, CreateManualLeadData $data, CreateManualLeadHandler $create): JsonResponse
    {
        $company = $create->handle($data, (int) $request->user()->id);

        return response()->json(['data' => CompanyData::fromEntity($company)], 201);
    }
}
