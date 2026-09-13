<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

/**
 * Structured content of a practice artifact (clarify D20).
 */
enum ContentBlockType: string
{
    case Heading = 'heading';
    case Paragraph = 'paragraph';
    case List = 'list';
    case Table = 'table';
    case KeyValues = 'key_values';
    case Footer = 'footer';
}
