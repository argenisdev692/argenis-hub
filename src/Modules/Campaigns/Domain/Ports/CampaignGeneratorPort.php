<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Ports;

use Modules\Campaigns\Application\DTOs\CampaignDraftData;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Domain\Services\CampaignQualityEvaluator;

/**
 * Step 2, the writing half: one TEXT-ONLY generation attempt (fresh Tavily
 * research + Facebook/Instagram copy + image concepts). Read-only with respect
 * to the aggregate — the caller decides whether to iterate again or keep the
 * result.
 *
 * Exactly ONE attempt per call, and nothing billed beyond a single text
 * completion: the loop is orchestrated by the Application handler, scoring
 * belongs to {@see CampaignEvaluatorPort}, and artwork to
 * {@see CampaignAssetRendererPort}. Splitting the three is what makes a
 * rejected attempt cheap.
 *
 * `$iteration` and `$previousWeaknesses` are 1/empty on the first call; from
 * iteration 2 onward the caller passes back
 * {@see CampaignQualityEvaluator::identifyWeaknesses()} so the agent targets
 * the specific scores the judge failed instead of a blind retry.
 */
interface CampaignGeneratorPort
{
    /**
     * @param  list<array{score: string, current: int, target: int, gap: int, explanation: string}>  $previousWeaknesses
     */
    public function generate(
        string $campaignUuid,
        GenerateCampaignData $data,
        int $iteration = 1,
        array $previousWeaknesses = [],
        ?object $causer = null,
    ): CampaignDraftData;
}
