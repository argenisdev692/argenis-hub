<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Enums;

enum RequirementNature: string
{
    case Hard = 'hard';
    case Soft = 'soft';
}
