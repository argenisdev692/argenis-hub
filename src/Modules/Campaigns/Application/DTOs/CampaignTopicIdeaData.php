<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Modules\Campaigns\Domain\Ports\CampaignIdeatorPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One of the exactly-10 candidate angles returned by
 * {@see CampaignIdeatorPort}. `funnelStage`
 * (TOFU/MOFU/BOFU/LOYALTY) is assigned by the agent, not the user — it later
 * drives which CTA and Meta-objective rules
 * {@see GenerateCampaignData} applies.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CampaignTopicIdeaData extends Data
{
    public function __construct(
        public string $title,
        public string $angle,
        public string $hook,
        public string $platform,
        public int $estimatedVirality,
        public string $estimatedEngagement,
        public int $estimatedRoi,
        public int $estimatedLeadPotential,
        public string $difficulty,
        public string $whyItWorks,
        public string $keyTrend,
        public string $suggestedFormat,
        public string $contentType,
        public string $funnelStage,
    ) {}
}
