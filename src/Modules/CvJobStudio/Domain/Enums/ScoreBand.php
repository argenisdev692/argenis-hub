<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Enums;

enum ScoreBand: string
{
    case Strong = 'strong';
    case Good = 'good';
    case Apply = 'apply';
    case Consider = 'consider';
    case Skip = 'skip';
}
