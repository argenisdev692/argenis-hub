<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Who proposed a cut decision (EX-1).
 */
enum DecisionOrigin: string
{
    case SystemDetection = 'system_detection';
    case User = 'user';

    /** V2 — derived from a speech transcript rather than from the waveform. */
    case Transcription = 'transcription';

    /** V3 — proposed by the AI analyzer, and confidence-gated before it applies. */
    case Ai = 'ai';
}
