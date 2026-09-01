<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Ports;

use Modules\Campaigns\Application\DTOs\CampaignTopicIdeaData;
use Modules\Campaigns\Application\DTOs\SuggestCampaignTopicsData;

/**
 * Step 1: exactly 10 Meta Ads lead-gen campaign angle candidates for a niche,
 * each pre-classified into a TOFU/MOFU/BOFU/LOYALTY funnel stage. Read-only —
 * the caller picks one and feeds it into {@see CampaignGeneratorPort}.
 */
interface CampaignIdeatorPort
{
    /**
     * @return list<CampaignTopicIdeaData>
     */
    public function suggestTopics(SuggestCampaignTopicsData $data, ?object $causer = null): array;
}
