<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\Commands;

use Modules\Post\Application\Commands\SuggestPostTopicsHandler;
use Modules\SocialMedia\Application\DTOs\SocialMediaTopicIdeaData;
use Modules\SocialMedia\Application\DTOs\SuggestSocialMediaTopicsData;
use Modules\SocialMedia\Domain\Ports\SocialMediaTopicIdeatorPort;
use Shared\Domain\Ports\AuditPort;

/**
 * Imperative AI action (no DB write) — mirrors
 * {@see SuggestPostTopicsHandler}. Every
 * call is a real, billed provider request, so it is meta-audited even though
 * nothing is persisted yet. Authorization (permission:CREATE_SOCIAL_MEDIA) is
 * enforced at the route.
 */
final readonly class SuggestSocialMediaTopicsHandler
{
    public function __construct(
        private SocialMediaTopicIdeatorPort $ideator,
        private AuditPort $audit,
    ) {}

    /**
     * @return list<SocialMediaTopicIdeaData>
     */
    public function handle(SuggestSocialMediaTopicsData $data, ?object $causer = null): array
    {
        $ideas = $this->ideator->suggestTopics($data, $causer);

        $this->audit->log(
            event: 'social_media.ai.topics_suggested',
            properties: [
                'provider' => $data->provider,
                'language' => $data->language,
                'niche' => $data->niche,
                'idea_count' => count($ideas),
            ],
            causer: $causer,
            logName: 'social_media',
        );

        return $ideas;
    }
}
