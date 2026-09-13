<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Exceptions;

use RuntimeException;

/**
 * A course already holds the configured maximum of content files or style
 * references (FR-1b, FR-10).
 */
final class SourceDocumentLimitException extends RuntimeException
{
    public const string CODE = 'document_limit_reached';

    public function __construct(public readonly int $limit)
    {
        parent::__construct(sprintf('This course already has the maximum of %d files of this kind.', $limit));
    }
}
