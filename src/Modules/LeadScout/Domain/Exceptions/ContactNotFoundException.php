<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * Contact row missing. Rendered as 404.
 */
final class ContactNotFoundException extends RuntimeException
{
    public const string CODE = 'CONTACT_NOT_FOUND';

    public function __construct(string $uuid)
    {
        parent::__construct("Contact {$uuid} not found.");
    }
}
