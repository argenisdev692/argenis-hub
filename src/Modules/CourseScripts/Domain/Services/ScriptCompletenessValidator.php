<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;

/**
 * Gate B — the whole draft, after sections, closing and artifacts are written
 * (plan §3.5 step 9, FR-44a):
 *
 * - every section has segments; narration exists (FR-30)
 * - prompts only when the course teaches a tool (FR-30a), and never read aloud
 *   word for word by the narration
 * - closing parts present, including the student's practical exercise; next
 *   video iff one follows; organisations known (FR-33b)
 * - practice references resolve both ways, artifacts all written (FR-36b, FR-39)
 * - artifact tables add up, contact data invented (FR-36d, FR-39b)
 */
final readonly class ScriptCompletenessValidator
{
    /**
     * Consecutive prompt words a narration may not repeat: long enough that a
     * quoted phrase passes, short enough that reading a sentence out loud fails.
     */
    private const int READ_ALOUD_RUN_WORDS = 8;

    public function __construct(
        private TableArithmeticValidator $tables,
        private ContactDataValidator $contacts,
        private BibleRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>|null  $bible
     * @return list<string>
     */
    #[\NoDiscard]
    public function violations(ScriptDraft $draft, bool $hasNextVideo, ?array $bible): array
    {
        return [
            ...$this->segments($draft),
            ...$this->closing($draft, $hasNextVideo, $bible),
            ...$this->practiceReferences($draft),
            ...$this->artifacts($draft),
        ];
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public function segments(ScriptDraft $draft): array
    {
        $violations = [];
        $hasNarration = false;

        foreach ($draft->sections as $section) {
            $segments = (array) ($section['segments'] ?? []);

            if ($segments === []) {
                $violations[] = sprintf('Section %s has no content.', $section['number']);
            }

            foreach ($segments as $segment) {
                $type = (string) ($segment['type'] ?? '');
                $hasNarration = $hasNarration || $type === 'narration';

                if ($type === 'on_screen_prompt' && ! $draft->usesTool) {
                    $violations[] = sprintf('Section %s contains an on-screen prompt, but this course teaches no tool to type into.', $section['number']);
                }

                if ($type === 'on_screen_prompt' && trim((string) ($segment['prompt'] ?? '')) === '') {
                    $violations[] = sprintf('Section %s has an empty on-screen prompt.', $section['number']);
                }

                if ($type === 'on_screen_table' && (array) ($segment['table_columns'] ?? []) === []) {
                    $violations[] = sprintf('Section %s has an on-screen table without columns.', $section['number']);
                }
            }

            if ($this->narrationReadsPromptAloud($segments)) {
                $violations[] = sprintf(
                    'Section %s narration reads the on-screen prompt aloud word for word: the prompt is pasted, so the narration must summarise its intent in one sentence instead.',
                    $section['number'],
                );
            }
        }

        if (! $hasNarration) {
            $violations[] = 'The script contains no narration.';
        }

        return $violations;
    }

    /**
     * True when any narration of the section repeats a run of
     * {@see self::READ_ALOUD_RUN_WORDS} consecutive words of one of its prompts.
     * Reading a prompt out loud slows the video down; a shorter prompt, or a
     * phrase or two quoted from it, stays below the run and is fine.
     *
     * @param  array<int, mixed>  $segments
     */
    private function narrationReadsPromptAloud(array $segments): bool
    {
        $promptRuns = [];
        $narrations = [];

        foreach ($segments as $segment) {
            $type = (string) ($segment['type'] ?? '');

            if ($type === 'on_screen_prompt') {
                $promptRuns = [...$promptRuns, ...self::wordRuns((string) ($segment['prompt'] ?? ''))];
            } elseif ($type === 'narration') {
                $narrations[] = (string) ($segment['text'] ?? '');
            }
        }

        if ($promptRuns === []) {
            return false;
        }

        $promptRuns = array_flip($promptRuns);

        foreach ($narrations as $narration) {
            foreach (self::wordRuns($narration) as $run) {
                if (isset($promptRuns[$run])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string> every run of consecutive normalised words
     */
    private static function wordRuns(string $text): array
    {
        $words = mb_strtolower($text)
            |> (static fn (string $lower): string => (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $lower))
            |> trim(...)
            |> (static fn (string $clean): array => $clean === '' ? [] : explode(' ', $clean));

        $runs = [];

        for ($start = 0; $start + self::READ_ALOUD_RUN_WORDS <= count($words); $start++) {
            $runs[] = implode(' ', array_slice($words, $start, self::READ_ALOUD_RUN_WORDS));
        }

        return $runs;
    }

    /**
     * @param  array<string, mixed>|null  $bible
     * @return list<string>
     */
    #[\NoDiscard]
    public function closing(ScriptDraft $draft, bool $hasNextVideo, ?array $bible): array
    {
        $closing = $draft->closing;

        if ($closing === null) {
            return ['The closing parts (summary, recording notes, verification checklist) are missing.'];
        }

        $violations = [];
        $notes = (array) ($closing['recording_notes'] ?? []);

        if ((array) ($closing['summary_points'] ?? []) === []) {
            $violations[] = 'The summary (RESUMEN) is empty.';
        }

        if ((array) ($closing['verification_checklist'] ?? []) === []) {
            $violations[] = 'The final verification checklist is empty.';
        }

        $exercise = (array) ($closing['practice_exercise'] ?? []);

        if (trim((string) ($exercise['scenario'] ?? '')) === '' || trim((string) ($exercise['task'] ?? '')) === '') {
            $violations[] = 'The practical exercise (EJERCICIO PRÁCTICO) for the student needs a realistic scenario and a task.';
        }

        if ((array) ($exercise['success_criteria'] ?? []) === []) {
            $violations[] = 'The practical exercise (EJERCICIO PRÁCTICO) needs success criteria the student can check.';
        }

        if ((array) ($notes['preparation'] ?? []) === []) {
            $violations[] = 'Recording notes need a preparation list.';
        }

        if ((array) ($notes['tools_required'] ?? []) === [] && trim((string) ($notes['tools_none_reason'] ?? '')) === '') {
            $violations[] = 'Recording notes must list the tools or connectors required, or say explicitly that none are needed.';
        }

        $handoff = trim((string) ($closing['next_video_handoff'] ?? ''));

        if ($hasNextVideo && $handoff === '') {
            $violations[] = 'A following video exists but the closing has no next-video handoff.';
        }

        if (! $hasNextVideo && $handoff !== '') {
            $violations[] = 'This is the last video, so it must not hand off to a next one.';
        }

        $preparation = mb_strtolower(implode(' ', array_map(strval(...), (array) ($notes['preparation'] ?? []))));

        foreach ($draft->plannedFileNames() as $file) {
            if (! str_contains($preparation, mb_strtolower($file))) {
                $violations[] = sprintf('Recording notes preparation must name the practice file "%s".', $file);
            }
        }

        $planOrganisations = [];

        foreach ((array) ($draft->practicePlan['artifacts'] ?? []) as $artifact) {
            $planOrganisations = [...$planOrganisations, ...array_map(strval(...), (array) ($artifact['organisations'] ?? []))];
        }

        foreach ($draft->artifacts as $artifact) {
            foreach ((array) ($artifact['organisations'] ?? []) as $organisation) {
                $planOrganisations[] = (string) ($organisation['name'] ?? '');
            }
        }

        foreach ((array) ($notes['organisations_used'] ?? []) as $organisation) {
            $name = (string) $organisation;
            $inPlan = array_filter($planOrganisations, fn (string $planned): bool => $this->registry->normalise($planned) === $this->registry->normalise($name)) !== [];

            if (trim($name) !== '' && ! $inPlan && ! $this->registry->isKnownOrganisation($bible, $name)) {
                $violations[] = sprintf('Organisation "%s" is neither in the course bible nor introduced by the practice pack.', $name);
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public function practiceReferences(ScriptDraft $draft): array
    {
        $files = $draft->plannedFileNames();
        $referenced = [];
        $violations = [];

        foreach ($draft->sections as $section) {
            foreach ((array) ($section['practice_files'] ?? []) as $file) {
                $referenced[(string) $file] = true;
            }

            foreach ((array) ($section['segments'] ?? []) as $segment) {
                $file = $segment['practice_file'] ?? null;

                if ($file === null || $file === '') {
                    continue;
                }

                $referenced[(string) $file] = true;

                if (! in_array($file, $files, true)) {
                    $violations[] = sprintf('Section %s shows practice file "%s", which is not in the practice pack.', $section['number'], $file);
                }
            }
        }

        foreach ($files as $file) {
            if (! isset($referenced[$file])) {
                $violations[] = sprintf('Practice file "%s" is never used by the script.', $file);
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public function artifacts(ScriptDraft $draft): array
    {
        $violations = [];

        foreach ($draft->plannedFileNames() as $file) {
            $artifact = $draft->artifacts[$file] ?? null;

            if ($artifact === null || (array) ($artifact['content_blocks'] ?? []) === []) {
                $violations[] = sprintf('Practice file "%s" has no content.', $file);

                continue;
            }

            $violations = [
                ...$violations,
                ...$this->tables->violations($file, (array) $artifact['content_blocks']),
                ...$this->contacts->violations($file, (array) $artifact['content_blocks']),
            ];
        }

        return $violations;
    }
}
