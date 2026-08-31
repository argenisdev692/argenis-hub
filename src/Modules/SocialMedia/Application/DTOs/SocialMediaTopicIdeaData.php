<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Modules\SocialMedia\Domain\Ports\SocialMediaTopicIdeatorPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One of the exactly-10 candidate topics returned by
 * {@see SocialMediaTopicIdeatorPort}.
 * `funnelStage` (TOFU/MOFU/BOFU) is assigned by the agent, not the user — it
 * later drives which CTA rules {@see GenerateSocialMediaContentData} applies.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class SocialMediaTopicIdeaData extends Data
{
    public function __construct(
        public string $title,
        public string $angle,
        public string $hook,
        public string $platform,
        public int $estimatedVirality,
        public string $estimatedEngagement,
        public int $estimatedRoi,
        public string $difficulty,
        public string $whyItWorks,
        public string $keyTrend,
        public string $suggestedFormat,
        public string $contentType,
        public string $funnelStage,
    ) {}
}
