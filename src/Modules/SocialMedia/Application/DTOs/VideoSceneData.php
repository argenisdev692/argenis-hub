<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One CapCut timeline row for a short-form video package (TikTok / Instagram Reels).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class VideoSceneData extends Data
{
    public function __construct(
        public string $timeRange,
        public string $action,
        public string $onScreenText,
        public string $voiceoverLine,
        public string $visualPrompt,
    ) {}
}
