<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Ports;

use Modules\Campaigns\Application\DTOs\CampaignDraftData;
use Modules\Campaigns\Application\DTOs\CampaignEvaluationData;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Domain\Services\CampaignQualityEvaluator;

/**
 * The judging half of the quality gate: an INDEPENDENT model scores a draft
 * produced by {@see CampaignGeneratorPort}.
 *
 * Independence is the whole point. While one agent both wrote and scored the
 * ad, the gate measured the writer's opinion of itself, which is uniformly
 * generous — the loop exited on iteration 1 with inflated numbers and the
 * thresholds in {@see CampaignQualityEvaluator} never bit. The implementation
 * therefore runs on `config('ai.default_for_evaluation')`, not on the caller's
 * writing provider.
 */
interface CampaignEvaluatorPort
{
    public function evaluate(
        string $campaignUuid,
        CampaignDraftData $draft,
        GenerateCampaignData $data,
        int $iteration = 1,
        ?object $causer = null,
    ): CampaignEvaluationData;
}
