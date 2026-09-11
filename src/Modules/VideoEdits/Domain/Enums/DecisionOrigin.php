<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Who proposed a cut decision (EX-1). V2 adds `transcription`, V3 adds `ai`.
 */
enum DecisionOrigin: string
{
    case SystemDetection = 'system_detection';
    case User = 'user';
}
