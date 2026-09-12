<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Why a time range is proposed for removal (EX-1 reason taxonomy).
 *
 * V1 emits `Silence` and `Manual`; V2 the five speech disfluencies; V3 only
 * `PauseMarker` and `Retake`.
 *
 * V3 deliberately adds NO reason for off-script or redundant content: those are
 * editorial judgements with no exact boundary, so they are reported as
 * {@see AiRecommendationKind} recommendations instead of being cut
 * automatically (decision R6/R7). A reason existing here means "this can be
 * removed by a machine without asking".
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

    // V3 — AI edit (US-13). Both are unambiguous errors, never taste.
    case PauseMarker = 'pause_marker';
    case Retake = 'retake';
}
