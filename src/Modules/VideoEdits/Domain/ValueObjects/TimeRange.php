<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Half-open interval [start, end) in integer milliseconds on the merged timeline.
 */
final readonly class TimeRange
{
    public function __construct(
        public int $startMs,
        public int $endMs,
    ) {
        if ($startMs < 0) {
            throw new InvalidArgumentException('A time range cannot start before 0 ms.');
        }

        if ($endMs <= $startMs) {
            throw new InvalidArgumentException('A time range must end after it starts.');
        }
    }

    public function durationMs(): int
    {
        return $this->endMs - $this->startMs;
    }
}
