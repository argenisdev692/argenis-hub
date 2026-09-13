<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Modules\CourseScripts\Domain\ValueObjects\IncomingDocument;

/**
 * The upload use-case input: title, index and optional content files (US-1).
 * Built by the controller from a validated request.
 */
final readonly class StoreCourseInput
{
    /**
     * @param  list<IncomingDocument>  $contents
     */
    public function __construct(
        public ?string $title,
        public IncomingDocument $index,
        public array $contents = [],
    ) {}
}
