<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The abandoned attempt a speaker was correcting when they said "pausa".
 *
 * A `CutDecision` cannot describe this, and neither can a `SpeechDetection`: the
 * marker itself is one of those, and the take is everything BEFORE it. What the
 * range cannot say either is *why* it stops where it does, which is the only
 * thing that tells the owner whether to trust it — so the evidence that closed
 * the walk-back travels with the range as {@see self::$boundary} and the
 * confidence it earns.
 *
 * Word indices are carried as well as milliseconds because the review shows the
 * words either side of the span, and the transcript is the only thing that has
 * them.
 */
final readonly class FailedTake
{
    /**
     * @param  int  $startWordIndex  inclusive
     * @param  int  $endWordIndex  inclusive
     */
    public function __construct(
        public int $startMs,
        public int $endMs,
        public int $startWordIndex,
        public int $endWordIndex,
        public FailedTakeBoundary $boundary,
        /**
         * Two independent signals agreed on this span — a spoken marker AND the
         * clause repeated after a hesitation. Raises it above every other
         * boundary, because the two signals fail independently: the marker is
         * dropped by the transcriber, the repetition is not.
         */
        public bool $corroborated = false,
    ) {
        if ($startMs < 0 || $endMs <= $startMs) {
            throw new InvalidArgumentException('A failed take must span a positive time range.');
        }

        if ($startWordIndex < 0 || $endWordIndex < $startWordIndex) {
            throw new InvalidArgumentException('A failed take must span an ordered, non-negative word range.');
        }
    }

    public function wordCount(): int
    {
        return $this->endWordIndex - $this->startWordIndex + 1;
    }

    public function durationMs(): int
    {
        return $this->endMs - $this->startMs;
    }
}
