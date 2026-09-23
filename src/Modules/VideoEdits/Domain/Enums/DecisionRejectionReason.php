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
}
