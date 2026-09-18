<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\LeadScout\Application\Commands\GetAiSettingsHandler;
use Modules\LeadScout\Application\Commands\UpdateAiSettingsHandler;
use Modules\LeadScout\Application\DTOs\UpdateAiSettingsData;

/**
 * AI settings endpoints (spec US-10, plan §5).
 */
final readonly class AiSettingsController
{
    public function show(GetAiSettingsHandler $get): JsonResponse
    {
        return response()->json(['data' => $get->handle()]);
    }

    public function update(UpdateAiSettingsData $data, UpdateAiSettingsHandler $update): JsonResponse
    {
        return response()->json(['data' => $update->handle($data)]);
    }
}
