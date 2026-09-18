<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\LeadScout\Application\Commands\UpdateOutreachStageHandler;
use Modules\LeadScout\Application\DTOs\OutreachData;
use Modules\LeadScout\Application\DTOs\UpdateOutreachData;

/**
 * Outreach stage endpoint (spec US-5/US-6, plan §5). The operator sends
 * every message by hand and records it here with the used channel.
 */
final readonly class OutreachController
{
    public function update(Request $request, string $uuid, UpdateOutreachData $data, UpdateOutreachStageHandler $update): JsonResponse
    {
        $result = $update->handle($uuid, $data, (int) $request->user()->id);
        $result['outreach']->loadMissing(['company', 'contactChannel']);

        return response()->json([
            'data' => OutreachData::fromModel($result['outreach'], $result['outreach']->company->uuid),
            'meta' => [
                'daily_sent' => $result['daily_sent'],
                'daily_limit_warning' => $result['daily_limit_warning'],
            ],
        ]);
    }
}
