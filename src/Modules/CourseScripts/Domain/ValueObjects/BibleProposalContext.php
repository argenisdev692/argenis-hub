<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * What the bible proposal is derived from: title, table of contents and the
 * author's own notes (FR-11).
 */
final readonly class BibleProposalContext
{
    /**
     * @param  list<string>  $tableOfContents  "N. Title" lines, in course order
     */
    public function __construct(
        public string $courseTitle,
        public string $language,
        public array $tableOfContents,
        public ?string $courseNotes,
        public ?string $sampleBriefs,
        public ?string $styleExemplar = null,
    ) {}
}
