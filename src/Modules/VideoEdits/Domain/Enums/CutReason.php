<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Why a time range is proposed for removal (EX-1 reason taxonomy).
 *
 * V1 producers emit `Silence` and `Manual`. The taxonomy is open: V2 adds
 * speech reasons (filler, filler_word, stutter, repetition, vocal_sound) and V3
 * adds AI reasons (pause_marker, retake, script_error, off_script, redundant)
 * as new cases — validation, rendering and persistence stay unchanged.
 */
enum CutReason: string
{
    case Silence = 'silence';
    case Manual = 'manual';
}
