<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Enums;

enum SkillRelationKind: string
{
    case Alias = 'alias';
    case Family = 'family';
}
