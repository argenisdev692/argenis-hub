<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * The person objected and cannot be added again (spec US-11 CA-10).
 * Rendered as 409.
 */
final class PersonOpposedException extends RuntimeException
{
    public const string CODE = 'PERSON_OPPOSED';

    public function __construct()
    {
        parent::__construct('This person objected and cannot be added again.');
    }
}
