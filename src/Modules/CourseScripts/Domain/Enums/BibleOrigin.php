<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

enum BibleOrigin: string
{
    case Proposed = 'proposed';
    case Author = 'author';
}
