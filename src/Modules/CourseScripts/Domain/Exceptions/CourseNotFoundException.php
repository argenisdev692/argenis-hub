<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * The requested course, run, video, version or deliverable does not exist FOR
 * THIS USER (spec FR-53).
 *
 * One exception covers "absent" and "belongs to someone else" on purpose: the
 * spec requires a foreign identifier to behave as not found, so distinguishing
 * them in the type would invite a controller to leak the difference as 403.
 */
final class CourseNotFoundException extends RuntimeException
{
    public const string CODE = 'not_found';

    public function __construct()
    {
        parent::__construct('Course not found.');
    }
}
