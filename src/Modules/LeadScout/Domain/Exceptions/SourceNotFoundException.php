<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * Job source missing. Rendered as 404.
 */
final class SourceNotFoundException extends RuntimeException
{
    public const string CODE = 'SOURCE_NOT_FOUND';

    public function __construct(string $uuid)
    {
        parent::__construct("Source {$uuid} not found.");
    }
}
