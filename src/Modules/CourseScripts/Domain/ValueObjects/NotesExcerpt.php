<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * A piece of the author's own material handed to the writer (FR-4d).
 * `sourceId` is recorded on the script version: `video_notes`, `course_notes`
 * or `document:{uuid}`.
 */
final readonly class NotesExcerpt
{
    public function __construct(
        public string $sourceId,
        public string $label,
        public string $text,
    ) {}
}
