<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

enum SourceDocumentKind: string
{
    case Index = 'index';
    case Content = 'content';
    case StyleReference = 'style_reference';
}
