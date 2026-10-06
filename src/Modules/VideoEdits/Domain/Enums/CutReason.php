<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Why a time range is proposed for removal (EX-1 reason taxonomy).
 *
 * V1 emits `Silence` and `Manual`; V2 the five speech disfluencies; V3
 * `PauseMarker`, `Retake`, `Misspoken` and `MisplacedTake`.
 *
 * V3 adds NO reason for rambling or redundant content: those are editorial
 * judgements with no exact boundary, so they are reported as
 * {@see AiRecommendationKind} recommendations (decision R6/R7). The four V3
 * reasons are word-bounded errors, and none of them is applied until the owner
 * approves it in the cut review.
 */
enum CutReason: string
{
    case Silence = 'silence';
    case Manual = 'manual';

    // V2 — speech detection (US-10).
    case Filler = 'filler';
    case FillerWord = 'filler_word';
    case Stutter = 'stutter';
    case Repetition = 'repetition';
    case VocalSound = 'vocal_sound';

    // V3 — AI edit (US-13). Word-bounded errors, never taste.
    case PauseMarker = 'pause_marker';
    case Retake = 'retake';
    // A word or phrase said wrong against the script, with no "PAUSA" flagging it.
    case Misspoken = 'misspoken';
    /**
     * A stretch of narration that is perfectly well delivered but does not
     * belong where it sits — typically because a scene change was missed and
     * the explanation of one step is playing over the screen of another. Nothing
     * was said wrong, so {@see self::Misspoken} does not fit, and there is no
     * pause marker to hang {@see self::Retake} on, but the block still has to
     * go: the same words are spoken again later, where the picture agrees.
     */
    case MisplacedTake = 'misplaced_take';

    /**
     * The only reasons the AI analyzer may propose as cuts, whatever the model
     * returns.
     */
    public function isAiProposable(): bool
    {
        return in_array($this, [
            self::PauseMarker,
            self::Retake,
            self::Misspoken,
            self::MisplacedTake,
        ], true);
    }
}
