<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Campaigns\Application\Commands\GenerateCampaignHandler;
use Modules\Campaigns\Application\Commands\SuggestCampaignTopicsHandler;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Application\DTOs\SuggestCampaignTopicsData;
use Modules\Campaigns\Application\Queries\GetCampaignHandler;

/**
 * XHR-only AI actions for the 2-step wizard. Step 1 (`suggestTopics`) is
 * synchronous — it is a single provider call, same cost profile as Post's
 * AI-assist. Step 2 (`generateCampaign`) returns immediately with a
 * `generating` row; the frontend polls `status()` or subscribes to
 * `campaigns.ai.progress` on its private channel instead of blocking on the
 * up-to-5-iteration quality loop. Gated by `permission:CREATE_CAMPAIGNS` +
 * a tight `throttle:` (real, billed provider cost per call) — see Routes.
 */
final readonly class CampaignAiAssistController
{
    public function __construct(
        private SuggestCampaignTopicsHandler $suggestTopics,
        private GenerateCampaignHandler $generateCampaign,
        private GetCampaignHandler $getCampaign,
    ) {}

    public function suggestTopics(SuggestCampaignTopicsData $data, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->suggestTopics->handle($data, $request->user())]);
    }

    public function generateCampaign(GenerateCampaignData $data, Request $request): JsonResponse
    {
        $campaign = $this->generateCampaign->handle($data, $request->user());

        return response()->json(['data' => $campaign], 202);
    }

    public function status(string $uuid): JsonResponse
    {
        return response()->json(['data' => $this->getCampaign->handle($uuid)]);
    }
}
