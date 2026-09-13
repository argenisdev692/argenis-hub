<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

enum PracticeDecisionOrigin: string
{
    case System = 'system';
    case AuthorForced = 'author_forced';
}
