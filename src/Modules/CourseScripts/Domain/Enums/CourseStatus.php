<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

/**
 * Lifecycle of a course as a whole, derived from its videos' script states.
 */
enum CourseStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Generating = 'generating';
    case PartiallyGenerated = 'partially_generated';
    case Completed = 'completed';
}
