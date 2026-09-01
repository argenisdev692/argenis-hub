<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Modules\Campaigns\Application\DTOs\CampaignTopicIdeaData;
use Modules\Campaigns\Application\DTOs\SuggestCampaignTopicsData;
use Modules\Campaigns\Domain\Ports\CampaignIdeatorPort;
use Modules\SocialMedia\Application\Commands\SuggestSocialMediaTopicsHandler;
use Shared\Domain\Ports\AuditPort;

/**
 * Imperative AI action (no DB write) — mirrors
 * {@see SuggestSocialMediaTopicsHandler}.
 * Every call is a real, billed provider request, so it is meta-audited even
 * though nothing is persisted yet. Authorization (permission:CREATE_CAMPAIGNS)
 * is enforced at the route.
 */
final readonly class SuggestCampaignTopicsHandler
{
    public function __construct(
        private CampaignIdeatorPort $ideator,
        private AuditPort $audit,
    ) {}

    /**
     * @return list<CampaignTopicIdeaData>
     */
    public function handle(SuggestCampaignTopicsData $data, ?object $causer = null): array
    {
        $ideas = $this->ideator->suggestTopics($data, $causer);

        $this->audit->log(
            event: 'campaigns.ai.topics_suggested',
            properties: [
                'provider' => $data->provider,
                'language' => $data->language,
                'niche' => $data->niche,
                'idea_count' => count($ideas),
            ],
            causer: $causer,
            logName: 'campaigns',
        );

        return $ideas;
    }
}
