<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

enum DeliverableFormat: string
{
    case Md = 'md';
    case Pdf = 'pdf';

    public function mimeType(): string
    {
        return match ($this) {
            self::Md => 'text/markdown; charset=UTF-8',
            self::Pdf => 'application/pdf',
        };
    }
}
