<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * Everything needed to render a script and its prompts sheet (FR-45, FR-35a).
 * Markdown and PDF read the same object, so the two formats cannot diverge.
 */
final readonly class ScriptDocument
{
    /**
     * @param  array<string, mixed>  $technicalHeader
     * @param  list<string>  $learningObjectives
     * @param  list<array<string, mixed>>  $sections
     * @param  list<string>  $summaryPoints
     * @param  array<string, mixed>|null  $nextVideo
     * @param  array<string, mixed>  $recordingNotes
     * @param  list<string>  $verificationChecklist
     */
    public function __construct(
        public string $language,
        public string $courseTitle,
        public int $videoNumber,
        public string $videoTitle,
        public string $fileName,
        public string $promptsFileName,
        public int $version,
        public string $generatedOn,
        public array $technicalHeader,
        public array $learningObjectives,
        public string $continuityNote,
        public array $sections,
        public array $summaryPoints,
        public ?array $nextVideo,
        public array $recordingNotes,
        public array $verificationChecklist,
        public ?string $practiceDocumentName,
        public bool $isGrounded,
        public ?bool $passedReview,
    ) {}

    /**
     * Prompts in recording order, each with its section and the practice files
     * its section shows (FR-35a).
     *
     * @return list<array{section_number: string, section_title: string, demo_label: ?string, prompt: string, practice_files: list<string>}>
     */
    public function prompts(): array
    {
        $titles = [];
        $prompts = [];

        foreach ($this->sections as $section) {
            $titles[(string) $section['number']] = (string) $section['title'];
        }

        foreach ($this->sections as $section) {
            $files = array_values(array_unique(array_filter([
                ...array_map(strval(...), (array) ($section['practice_files'] ?? [])),
                ...array_map(static fn (array $segment): string => (string) ($segment['practice_file'] ?? ''), (array) ($section['segments'] ?? [])),
            ])));

            if ($files === [] && ($section['parent_number'] ?? null) !== null) {
                foreach ($this->sections as $parent) {
                    if ($parent['number'] === $section['parent_number']) {
                        $files = array_values(array_map(strval(...), (array) ($parent['practice_files'] ?? [])));
                    }
                }
            }

            foreach ((array) ($section['segments'] ?? []) as $segment) {
                if (($segment['type'] ?? '') !== 'on_screen_prompt' || trim((string) ($segment['prompt'] ?? '')) === '') {
                    continue;
                }

                $prompts[] = [
                    'section_number' => (string) $section['number'],
                    'section_title' => $titles[(string) $section['number']] ?? '',
                    'demo_label' => $section['demo_label'] ?? null,
                    'prompt' => (string) $segment['prompt'],
                    'practice_files' => $files,
                ];
            }
        }

        return $prompts;
    }
}
