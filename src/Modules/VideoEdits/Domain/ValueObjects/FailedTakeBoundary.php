<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * What stopped {@see FailedTakeDetector}'s walk back from a pause marker.
 *
 * The order IS the strength of the evidence, and each boundary carries the
 * confidence its evidence earns — which is what decides whether a proposal
 * starts ticked in the review or waits to be chosen. That is why this is an enum
 * and not a boolean: "we are not sure" is a real third answer, distinct from
 * both "a sentence ended here" and "we hit the ceiling".
 */
enum FailedTakeBoundary: string
{
    /**
     * Whisper wrote sentence punctuation on the word before the take. The
     * strongest evidence available without a model: the transcriber itself
     * decided that a sentence closed there.
     */
    case Sentence = 'sentence';

    /**
     * A pause at least `boundary_gap_ms` long sits at the edge of the take, so
     * the speaker had finished a thought before starting the one they dropped.
     */
    case Gap = 'gap';

    /**
     * The look-back ceiling was reached first. The take is real but its start is
     * the best guess available, so it is the one boundary that must never start
     * ticked.
     */
    case LookbackLimit = 'lookback_limit';

    /**
     * The walk reached the start of the recording without finding a boundary.
     * The same evidence as {@see self::LookbackLimit} and treated the same way —
     * nothing proved where the take began.
     */
    case RecordingStart = 'recording_start';

    public function confidence(): float
    {
        return match ($this) {
            self::Sentence => 0.95,
            self::Gap => 0.85,
            self::LookbackLimit, self::RecordingStart => 0.6,
        };
    }

    /**
     * Shown to the owner in the review, so the boundary can be checked rather
     * than trusted.
     */
    public function explanation(): string
    {
        return match ($this) {
            self::Sentence => 'The sentence before it was already finished, so the take cannot reach back any further.',
            self::Gap => 'A pause sits at the edge of the take, so the speaker had finished a thought before starting this one.',
            self::LookbackLimit => 'No sentence end or pause was found within the look-back limit, so the start of the take is a guess.',
            self::RecordingStart => 'The take reaches the start of the recording, so where it began is a guess.',
        };
    }
}
