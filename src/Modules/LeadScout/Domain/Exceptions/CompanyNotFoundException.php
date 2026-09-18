<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * Company row missing. Rendered as 404.
 */
final class CompanyNotFoundException extends RuntimeException
{
    public const string CODE = 'COMPANY_NOT_FOUND';

    public function __construct(string $uuid)
    {
        parent::__construct("Company {$uuid} not found.");
    }
}
