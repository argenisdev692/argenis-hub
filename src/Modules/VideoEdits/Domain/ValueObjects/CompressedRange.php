<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A stretch of the recording that is kept but played faster: the wait while an
 * AI tool (Claude, Cursor, Copilot…) writes its answer on screen. Cutting that
 * wait makes the result jump into place; speeding it up keeps the student
 * oriented while still saving the time.
 */
final readonly class CompressedRange
{
    public function __construct(
        public TimeRange $range,
        public float $speed,
    ) {
        if ($speed <= 1.0) {
            throw new InvalidArgumentException('A compressed range must play faster than real time.');
        }
    }

    /** How long this range lasts once sped up. */
    public function outputDurationMs(): int
    {
        return (int) ceil($this->range->durationMs() / $this->speed);
    }

    /** Time the speed-up saves against playing the range as recorded. */
    public function savedMs(): int
    {
        return $this->range->durationMs() - $this->outputDurationMs();
    }
}
