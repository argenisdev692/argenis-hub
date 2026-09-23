<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * How a silence with a moving screen is handled instead of being cut.
 *
 * The screen moving while nobody speaks is the signature of waiting on a tool
 * — an AI streaming its answer, an agent editing files, a document filling in —
 * whatever the tool is. The first and last moments stay at normal speed so the
 * viewer sees the request go out and the result land; only the middle is sped
 * up, and never to more than `maxOutputMs` on screen.
 */
final readonly class WaitCompressionPolicy
{
    /** Above this FFmpeg's `atempo` refuses the value. */
    private const float MAX_SPEED = 100.0;

    public function __construct(
        public bool $enabled,
        public float $minActivityRatio,
        public int $keepHeadMs,
        public int $keepTailMs,
        public float $speed,
        public int $maxOutputMs,
    ) {}

    public static function disabled(): self
    {
        return new self(false, 1.0, 0, 0, 2.0, 0);
    }

    public function appliesTo(?float $screenActivity): bool
    {
        return $this->enabled && $screenActivity !== null && $screenActivity >= $this->minActivityRatio;
    }

    /**
     * The sped-up middle of a wait, or null when the wait is too short to be
     * worth compressing — a short wait is simply kept as recorded.
     */
    public function compress(int $startMs, int $endMs): ?CompressedRange
    {
        $middleStart = $startMs + $this->keepHeadMs;
        $middleEnd = $endMs - $this->keepTailMs;
        $middleMs = $middleEnd - $middleStart;

        if ($middleMs <= 0) {
            return null;
        }

        $speed = min(self::MAX_SPEED, max($this->speed, $this->maxOutputMs > 0 ? $middleMs / $this->maxOutputMs : $this->speed));

        // Speeding up a sliver saves nothing and adds two joins.
        if ($middleMs - (int) ceil($middleMs / $speed) < 250) {
            return null;
        }

        return new CompressedRange(new TimeRange($middleStart, $middleEnd), round($speed, 3));
    }
}
