<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * The course already has a queued or running run (FR-25, D8).
 */
final class RunInProgressException extends RuntimeException
{
    public const string CODE = 'run_in_progress';

    public function __construct()
    {
        parent::__construct('This course is already being generated. Wait for the current run or cancel it.');
    }
}
