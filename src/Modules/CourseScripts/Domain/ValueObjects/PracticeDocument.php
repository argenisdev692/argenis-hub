<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * A practice pack ready to render (FR-36, FR-36e), in the layout of the author's
 * sample `Propuestas_Logistica_Heliantia`.
 */
final readonly class PracticeDocument
{
    /**
     * @param  list<array{demo_label: string, section_number: string, purpose: string, file_name?: string}>  $usage
     * @param  list<array<string, mixed>>  $designedContrasts
     * @param  list<array<string, mixed>>  $artifacts  each with file_name, title, content_blocks
     */
    public function __construct(
        public string $language,
        public int $videoNumber,
        public string $videoTitle,
        public string $documentName,
        public string $headerTitle,
        public string $filesSummary,
        public string $setupInstruction,
        public array $usage,
        public string $instructorNote,
        public array $designedContrasts,
        public array $artifacts,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function artifact(string $fileName): ?array
    {
        return array_find($this->artifacts, static fn (array $artifact): bool => $artifact['file_name'] === $fileName);
    }
}
