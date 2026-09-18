<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * The query was rejected BEFORE any provider call: professional network,
 * contact-data broker, or person-seeking wording (spec FR-13/FR-25,
 * clarify A17). Never billed, never retried.
 */
final class RejectedSearchQueryException extends RuntimeException
{
    public const string CODE = 'SEARCH_QUERY_REJECTED';

    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
