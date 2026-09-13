<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

enum DeliverableDocumentType: string
{
    case Script = 'script';
    case Prompts = 'prompts';
    case Practice = 'practice';
    case PracticeFile = 'practice_file';
}
