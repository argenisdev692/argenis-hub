<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * A script being written, step by step (plan §3.5). Immutable: each step
 * returns a new draft with its part filled in.
 *
 * Shapes (all keys snake_case, exactly as persisted):
 *
 *   section  = {number: string, parent_number: ?string, title: string, kind: string,
 *               minutes: int, purpose: string, demo_label: ?string, demo_purpose: ?string,
 *               practice_files: list<string>, segments: list<segment>}
 *   segment  = {type: string, text: string, prompt: string, items: list<string>,
 *               table_columns: list<string>, table_rows: list<list<string>>,
 *               read_aloud: bool, practice_file: ?string}
 *   plan     = {warranted: bool, reason: string, topic: string, files_summary: string,
 *               setup_instruction: string, instructor_note: string,
 *               artifacts: list<{file_name, title, genre, purpose, used_by_demos: list<string>,
 *                                organisations: list<string>}>,
 *               contrasts: list<{dimension, intended_effect, values: list<{file_name, value}>}>}
 *   closing  = {summary_points: list<string>, next_video_handoff: string,
 *               recording_notes: {preparation, during_recording, tools_required: list<string>,
 *                                 tools_none_reason: string, continuity: string,
 *                                 organisations_used: list<string>},
 *               verification_checklist: list<string>}
 *   artifact = {file_name, content_blocks: list<block>, organisations: list<{name, role, sector}>,
 *               characters: list<{name, role, organisation}>}
 *   block    = {type, level: int, text, items: list<string>, table_header: list<string>,
 *               table_rows: list<list<string>>, total_row: bool, pairs: list<{key, value}>}
 */
final readonly class ScriptDraft
{
    /**
     * @param  list<string>  $learningObjectives
     * @param  list<array<string, mixed>>  $sections
     * @param  list<array{item: string, section_numbers: list<string>}>  $coverageMap
     * @param  array<string, mixed>  $practicePlan
     * @param  array<string, mixed>|null  $closing
     * @param  array<string, array<string, mixed>>  $artifacts  keyed by file name
     */
    public function __construct(
        public string $recordingFormat,
        public array $learningObjectives,
        public string $continuityNote,
        public bool $usesTool,
        public array $sections,
        public array $coverageMap,
        public string $taughtSummary,
        public array $practicePlan,
        public ?array $closing = null,
        public array $artifacts = [],
    ) {}

    /**
     * @param  list<array<string, mixed>>  $sections
     */
    public function withSections(array $sections): self
    {
        return clone ($this, ['sections' => $sections]);
    }

    /**
     * @param  array<string, mixed>  $closing
     */
    public function withClosing(array $closing): self
    {
        return clone ($this, ['closing' => $closing]);
    }

    /**
     * @param  array<string, mixed>  $artifact
     */
    public function withArtifact(array $artifact): self
    {
        $artifacts = $this->artifacts;
        $artifacts[(string) $artifact['file_name']] = $artifact;

        return clone ($this, ['artifacts' => $artifacts]);
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    public function withPracticePlan(array $plan): self
    {
        return clone ($this, ['practicePlan' => $plan]);
    }

    public function practiceWarranted(): bool
    {
        return (bool) ($this->practicePlan['warranted'] ?? false);
    }

    /**
     * @return list<string>
     */
    public function plannedFileNames(): array
    {
        if (! $this->practiceWarranted()) {
            return [];
        }

        return array_values(array_map(
            static fn (array $artifact): string => (string) $artifact['file_name'],
            (array) ($this->practicePlan['artifacts'] ?? []),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function topLevelSections(): array
    {
        return array_values(array_filter($this->sections, static fn (array $section): bool => ($section['parent_number'] ?? null) === null));
    }

    /**
     * Every segment of every section, in order, with its section number.
     *
     * @return list<array<string, mixed>>
     */
    public function segments(): array
    {
        $segments = [];

        foreach ($this->sections as $section) {
            foreach ((array) ($section['segments'] ?? []) as $segment) {
                $segments[] = [...$segment, 'section_number' => $section['number']];
            }
        }

        return $segments;
    }

    /**
     * Plain text of the whole script, for keyword checks.
     */
    public function plainText(): string
    {
        $parts = [...$this->learningObjectives, $this->continuityNote];

        foreach ($this->sections as $section) {
            $parts[] = (string) $section['title'];

            foreach ((array) ($section['segments'] ?? []) as $segment) {
                $parts[] = (string) ($segment['text'] ?? '');
                $parts[] = (string) ($segment['prompt'] ?? '');
                $parts = [...$parts, ...array_map(strval(...), (array) ($segment['items'] ?? []))];
            }
        }

        return implode("\n", array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
