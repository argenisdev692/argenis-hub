<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Why a cut decision was not applied. Persisted next to the decision so the
 * summary (and the V3 report) can explain it.
 */
enum DecisionRejectionReason: string
{
    case NegativeStart = 'negative_start';
    case StartNotBeforeEnd = 'start_not_before_end';
    case EndBeyondDuration = 'end_beyond_duration';
    case ConfidenceOutOfRange = 'confidence_out_of_range';
    case ShorterThanPadding = 'shorter_than_padding';
    case OverlapsSpeech = 'overlaps_speech';
    /** The screen was busy (a tool working) — the wait was sped up or kept instead of cut. */
    case ScreenActivity = 'screen_activity';
    /**
     * The cut fell inside a tool wait (a screen busy while the speaker was
     * quiet — an AI streaming its answer, an agent writing files). Splicing
     * there shows the result appearing with nothing before it, so the cut is
     * refused instead of applied. Distinct from {@see self::ScreenActivity},
     * which is the rejection of the wait itself and its upgrade to a sped-up
     * stretch.
     */
    case ScreenBusy = 'screen_busy';
}
