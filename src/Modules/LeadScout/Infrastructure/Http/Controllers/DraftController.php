<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\LeadScout\Application\Commands\GenerateDraftHandler;
use Modules\LeadScout\Application\DTOs\GenerateDraftData;
use Modules\LeadScout\Application\DTOs\OutreachData;

/**
 * Draft generation endpoint (spec US-5/US-10, plan §5).
 */
final readonly class DraftController
{
    public function store(Request $request, string $uuid, GenerateDraftData $data, GenerateDraftHandler $generate): JsonResponse
    {
        $result = $generate->handle($uuid, $data, (int) $request->user()->id);

        return response()->json([
            'data' => OutreachData::fromEntity($result['outreach']),
            'meta' => [
                'subject' => $result['subject'],
                'unconfirmed_claims' => $result['unconfirmed_claims'],
                'warnings' => $result['warnings'],
            ],
        ], 201);
    }
}
