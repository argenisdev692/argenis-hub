<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Enums;

enum RequirementTag: string
{
    case Required = 'required';
    case Preferred = 'preferred';
    case Bonus = 'bonus';
}
