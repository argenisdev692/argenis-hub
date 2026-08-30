<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One row of the Reel/TikTok timeline-table deliverable.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ReelSceneData extends Data
{
    public function __construct(
        public string $timeRange,
        public string $action,
        public string $onScreenText,
        public string $voiceoverLine,
        public string $visualPrompt,
    ) {}
}
