<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Why a time range is proposed for removal (EX-1 reason taxonomy).
 *
 * V1 emits `Silence` and `Manual`; V2 the five speech disfluencies; V3
 * `PauseMarker`, `Retake` and `Misspoken`.
 *
 * V3 adds NO reason for rambling or redundant content: those are editorial
 * judgements with no exact boundary, so they are reported as
 * {@see AiRecommendationKind} recommendations (decision R6/R7). The three V3
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
     * The only reasons the AI analyzer may propose as cuts, whatever the model
     * returns.
     */
    public function isAiProposable(): bool
    {
        return $this === self::PauseMarker || $this === self::Retake || $this === self::Misspoken;
    }
}
