<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * Which signal found a {@see FailedTakeAnchor}.
 *
 * Kept as an enum rather than a boolean because the two are not equivalent
 * evidence and the owner is shown which one produced a proposal they are being
 * asked to approve.
 */
enum FailedTakeAnchorKind: string
{
    /**
     * The speaker said "pausa" out loud and Whisper transcribed it. A literal
     * match on a word the transcriber heard.
     */
    case PauseMarker = 'pause_marker';

    /**
     * The same clause said twice with a hesitation between, found by
     * {@see RestartDetector}. Weaker on its own — a speaker repeats for emphasis
     * too — but it works on recordings where the marker was never transcribed.
     */
    case Restart = 'restart';
}
