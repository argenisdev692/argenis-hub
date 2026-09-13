<?php

declare(strict_types=1);

namespace Modules\Cvs\Domain\Exceptions;

use RuntimeException;

/**
 * The requested CV does not exist FOR THIS USER.
 *
 * One exception covers "absent" and "belongs to someone else" on purpose: a
 * foreign UUID must behave as not found, so distinguishing them in the type
 * would invite a controller to leak the difference as 403 (OWASP §11).
 */
final class CvNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('CV not found.');
    }
}
