<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One CapCut timeline row for a Meta Reels/Stories video ad package.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CampaignVideoSceneData extends Data
{
    public function __construct(
        public string $timeRange,
        public string $action,
        public string $onScreenText,
        public string $voiceoverLine,
        public string $visualPrompt,
    ) {}
}
