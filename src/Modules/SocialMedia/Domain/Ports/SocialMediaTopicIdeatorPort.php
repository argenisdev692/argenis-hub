<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Ports;

use Modules\SocialMedia\Application\DTOs\SocialMediaTopicIdeaData;
use Modules\SocialMedia\Application\DTOs\SuggestSocialMediaTopicsData;

/**
 * Step 1: exactly 10 viral topic candidates for a niche, each pre-classified
 * into a TOFU/MOFU/BOFU funnel stage. Read-only — the caller picks one and
 * feeds it into {@see SocialMediaContentGeneratorPort}.
 */
interface SocialMediaTopicIdeatorPort
{
    /**
     * @return list<SocialMediaTopicIdeaData>
     */
    public function suggestTopics(SuggestSocialMediaTopicsData $data, ?object $causer = null): array;
}
