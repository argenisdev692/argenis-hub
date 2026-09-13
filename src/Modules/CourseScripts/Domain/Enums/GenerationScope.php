<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

enum GenerationScope: string
{
    case Course = 'course';
    case Block = 'block';
    case Selection = 'selection';
}
