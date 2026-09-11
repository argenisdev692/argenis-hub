<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * The uniform frame every result is delivered in (D3): the first clip's
 * aspect ratio scaled to fit the maximum box, never stretched, with the first
 * clip's frame rate capped. Other clips are scaled + padded into this frame.
 */
final readonly class OutputProfile
{
    private const float FALLBACK_FRAME_RATE = 30.0;

    public function __construct(
        public int $width,
        public int $height,
        public float $frameRate,
        public int $audioSampleRate,
        public int $audioChannels,
    ) {}

    public static function fromFirstSource(
        MediaProbe $first,
        int $maxWidth,
        int $maxHeight,
        int $maxFrameRate,
        int $audioSampleRate,
        int $audioChannels,
    ): self {
        $sourceWidth = $first->width ?? $maxWidth;
        $sourceHeight = $first->height ?? $maxHeight;
        $scale = min(1.0, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);

        return new self(
            width: self::even($sourceWidth * $scale),
            height: self::even($sourceHeight * $scale),
            frameRate: round(min($first->frameRate ?? self::FALLBACK_FRAME_RATE, (float) $maxFrameRate), 3),
            audioSampleRate: $audioSampleRate,
            audioChannels: $audioChannels,
        );
    }

    /**
     * x264 with yuv420p needs even dimensions.
     */
    private static function even(float $dimension): int
    {
        $floored = (int) floor($dimension);

        return max(2, $floored - ($floored % 2));
    }
}
