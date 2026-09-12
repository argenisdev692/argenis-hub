<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A sentence-level slice of a transcript.
 *
 * V2 detection cuts on {@see TranscriptWord}, never on segments — they are kept
 * because they are what a human reads, and because the V3 script-vs-transcript
 * comparison works at this granularity.
 */
final readonly class TranscriptSegment
{
    public function __construct(
        public string $text,
        public int $startMs,
        public int $endMs,
    ) {
        if ($startMs < 0 || $endMs < $startMs) {
            throw new InvalidArgumentException('A transcript segment must span a non-negative, ordered range.');
        }
    }
}
