<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * The CV has no extractable text (e.g. scanned PDF without `raw_text`).
 * Rendered as 422 with the reason (spec US-1 CA-5).
 */
final class CvNotImportableException extends RuntimeException
{
    public const string CODE = 'CV_NOT_IMPORTABLE';

    public function __construct(string $reason = 'The CV has no extractable text.')
    {
        parent::__construct($reason);
    }
}
