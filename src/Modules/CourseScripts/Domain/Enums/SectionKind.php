<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Enums;

/**
 * What a script section does. `Comparison` is the incorrect-vs-correct
 * demonstration pattern of Guion_Video_43 (FR-30b).
 */
enum SectionKind: string
{
    case Intro = 'intro';
    case Concept = 'concept';
    case Demo = 'demo';
    case Comparison = 'comparison';
    case Table = 'table';
    case Closing = 'closing';
}
