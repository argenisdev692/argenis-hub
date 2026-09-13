<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

enum GenerationRunKind: string
{
    case Generation = 'generation';
    case Regeneration = 'regeneration';
    case ForcePractice = 'force_practice';
}
