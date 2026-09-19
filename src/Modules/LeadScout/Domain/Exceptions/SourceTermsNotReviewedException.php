<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * A source cannot be activated before its terms are reviewed (spec FR-13). Rendered as 422.
 */
final class SourceTermsNotReviewedException extends RuntimeException
{
    public const string CODE = 'TERMS_NOT_REVIEWED';

    public function __construct()
    {
        parent::__construct('Review the source terms before activating it.');
    }
}
