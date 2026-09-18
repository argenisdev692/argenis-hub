<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * The company is suppressed (do-not-contact, objection or DGC list) and
 * therefore rejected everywhere with 409 (spec FR-17, FR-43).
 */
final class SuppressedException extends RuntimeException
{
    public const string CODE = 'COMPANY_SUPPRESSED';

    public function __construct(string $reason = 'The company is suppressed and cannot be proposed again.')
    {
        parent::__construct($reason);
    }
}
