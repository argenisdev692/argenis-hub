<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * CV row missing, deleted or owned by another user — indistinguishable
 * on purpose (OWASP §11). Rendered as 404.
 */
final class CvNotFoundException extends RuntimeException
{
    public const string CODE = 'CV_NOT_FOUND';

    public function __construct()
    {
        parent::__construct('CV not found.');
    }
}
