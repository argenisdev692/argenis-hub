<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * Neither the form nor the index supplied a course title (FR-1a).
 */
final class MissingCourseTitleException extends RuntimeException
{
    public const string CODE = 'title_required';

    public function __construct()
    {
        parent::__construct('Type a course title: the index does not state one.');
    }
}
