<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The same clause said twice with a hesitation in between: the signature of an
 * abandoned take.
 *
 * **Why this exists when there is already a pause-marker detector.** Because the
 * marker cannot be relied on. In this application's own recordings, Whisper
 * transcribed the spoken word "pausa" in some takes and dropped it entirely in
 * others — the marker moment came back as a bare "acá" with no marker word in the
 * transcript at all. A dictionary can only match what was transcribed, so on those
 * recordings the marker was simply invisible to every deterministic rule and the
 * audio kept it.
 *
 * The restart does not have that problem. When a speaker abandons an attempt, they
 * say the next thing again from the beginning, and that repetition is in the
 * transcript whether or not the marker survived. This is the signal that still
 * works when the marker does not.
 *
 * It is also the weaker signal, which is why it is never a cut on its own: a
 * speaker who restates a clause for emphasis repeats it too. The gap is what
 * separates them — an emphasis repeat follows immediately, a restart is preceded
 * by a hesitation the listener can hear — and everything this yields still goes
 * through the owner's review.
 */
final readonly class Restart
{
    public function __construct(
        /** Where the abandoned attempt began. */
        public int $failedStartMs,
        /** Where the speaker started again — the boundary the take is measured back from. */
        public int $restartStartMs,
        /** The silence between the end of the first copy and the start of the second. */
        public int $gapMs,
        /** The repeated clause, normalised — what both copies said. */
        public string $text,
    ) {
        if ($failedStartMs < 0 || $restartStartMs <= $failedStartMs) {
            throw new InvalidArgumentException('A restart must repeat a clause after its own first copy.');
        }
    }

    public function durationMs(): int
    {
        return $this->restartStartMs - $this->failedStartMs;
    }

    /**
     * Owner-facing explanation. The timing is the only part they can check, so it
     * is the part worth showing.
     */
    public function explanation(): string
    {
        return sprintf(
            'The same words are said again %s after a %.1f second pause, which is what restarting a sentence sounds like.',
            '"'.mb_substr($this->text, 0, 80).'"',
            $this->gapMs / 1000,
        );
    }
}
