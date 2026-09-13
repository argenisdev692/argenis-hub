<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

use Modules\CourseScripts\Domain\ValueObjects\ScriptDraft;

/**
 * Gate A — checks an outline before any section is written (plan §3.5 step 5,
 * FR-44a). A violation costs one outline retry instead of eight wasted calls.
 *
 * Covers: time budget (FR-29), mandatory-content coverage (FR-31), demo labels
 * (FR-29a), the practice plan (FR-36…FR-36c).
 */
final readonly class ScriptOutlineValidator
{
    public function __construct(
        private int $tolerancePct = 10,
        private int $minSections = 3,
        private int $maxSections = 12,
        private int $maxArtifacts = 4,
    ) {}

    /**
     * @param  list<string>  $mandatoryContent
     * @return list<string>
     */
    #[\NoDiscard]
    public function violations(ScriptDraft $draft, int $durationMinutes, array $mandatoryContent): array
    {
        return [
            ...$this->timeBudget($draft, $durationMinutes),
            ...$this->coverage($draft, $mandatoryContent),
            ...$this->demoLabels($draft),
            ...$this->practicePlan($draft),
        ];
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public function timeBudget(ScriptDraft $draft, int $durationMinutes): array
    {
        $violations = [];
        $top = $draft->topLevelSections();
        $count = count($top);

        if ($count < $this->minSections || $count > $this->maxSections) {
            $violations[] = sprintf('Use between %d and %d numbered top-level sections (got %d).', $this->minSections, $this->maxSections, $count);
        }

        $sum = array_sum(array_map(static fn (array $section): int => (int) $section['minutes'], $top));
        $tolerance = max(1.0, $durationMinutes * $this->tolerancePct / 100);

        if (abs($sum - $durationMinutes) > $tolerance) {
            $violations[] = sprintf('Top-level section minutes add up to %d; the video lasts %d minutes (tolerance ±%s).', $sum, $durationMinutes, rtrim(rtrim(number_format($tolerance, 1, '.', ''), '0'), '.'));
        }

        foreach ($top as $section) {
            $children = array_filter($draft->sections, static fn (array $child): bool => ($child['parent_number'] ?? null) === $section['number']);
            $childMinutes = array_sum(array_map(static fn (array $child): int => (int) $child['minutes'], $children));

            if ($childMinutes > (int) $section['minutes']) {
                $violations[] = sprintf('Sub-sections of section %s use %d minutes, more than the section\'s %d.', $section['number'], $childMinutes, $section['minutes']);
            }
        }

        $numbers = array_column($draft->sections, 'number');

        foreach ($draft->sections as $section) {
            $parent = $section['parent_number'] ?? null;

            if ($parent !== null && ! in_array($parent, $numbers, true)) {
                $violations[] = sprintf('Section %s points to a parent section %s that does not exist.', $section['number'], $parent);
            }
        }

        if (count($numbers) !== count(array_unique($numbers))) {
            $violations[] = 'Section numbers must be unique.';
        }

        return $violations;
    }

    /**
     * @param  list<string>  $mandatoryContent
     * @return list<string>
     */
    #[\NoDiscard]
    public function coverage(ScriptDraft $draft, array $mandatoryContent): array
    {
        $numbers = array_column($draft->sections, 'number');
        $covered = [];

        foreach ($draft->coverageMap as $entry) {
            $valid = array_intersect((array) $entry['section_numbers'], $numbers);

            if ($valid !== []) {
                $covered[$this->key((string) $entry['item'])] = true;
            }
        }

        $violations = [];

        foreach ($mandatoryContent as $item) {
            if (! isset($covered[$this->key($item)])) {
                $violations[] = sprintf('Mandatory content "%s" is not mapped to any existing section.', $item);
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public function demoLabels(ScriptDraft $draft): array
    {
        $labels = array_values(array_filter(array_map(
            static fn (array $section): ?string => $section['demo_label'] ?? null,
            $draft->sections,
        )));

        $violations = [];

        foreach ($labels as $index => $label) {
            $expected = 'DEMO '.($index + 1);

            if (strtoupper(trim($label)) !== $expected) {
                $violations[] = sprintf('Demo labels must be numbered in order: expected "%s", got "%s".', $expected, $label);

                break;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public function practicePlan(ScriptDraft $draft): array
    {
        $demoFiles = [];
        $demoLabels = [];

        foreach ($draft->sections as $section) {
            if (($section['demo_label'] ?? null) !== null) {
                $demoLabels[] = strtoupper((string) $section['demo_label']);
            }

            foreach ((array) ($section['practice_files'] ?? []) as $file) {
                $demoFiles[(string) $file] = true;
            }
        }

        if (! $draft->practiceWarranted()) {
            return $demoFiles === [] ? [] : ['Sections reference practice files but no practice pack is planned.'];
        }

        $plan = $draft->practicePlan;
        $files = $draft->plannedFileNames();
        $violations = [];

        if ($files === []) {
            return ['A practice pack is planned without any artifact.'];
        }

        if (count($files) > $this->maxArtifacts) {
            $violations[] = sprintf('A practice pack may contain at most %d artifacts.', $this->maxArtifacts);
        }

        if (count($files) !== count(array_unique(array_map(strtolower(...), $files)))) {
            $violations[] = 'Practice file names must be unique.';
        }

        foreach ($files as $file) {
            if (! isset($demoFiles[$file])) {
                $violations[] = sprintf('Practice file "%s" is not used by any demo section.', $file);
            }
        }

        foreach (array_keys($demoFiles) as $file) {
            if (! in_array($file, $files, true)) {
                $violations[] = sprintf('A section references practice file "%s", which the plan does not declare.', $file);
            }
        }

        foreach ((array) ($plan['artifacts'] ?? []) as $artifact) {
            foreach ((array) ($artifact['used_by_demos'] ?? []) as $label) {
                if (! in_array(strtoupper((string) $label), $demoLabels, true)) {
                    $violations[] = sprintf('Artifact "%s" says it is used by %s, which is not a demo of this script.', $artifact['file_name'], $label);
                }
            }
        }

        foreach ((array) ($plan['contrasts'] ?? []) as $contrast) {
            foreach ((array) ($contrast['values'] ?? []) as $value) {
                if (! in_array((string) ($value['file_name'] ?? ''), $files, true)) {
                    $violations[] = sprintf('Designed contrast "%s" refers to an undeclared file "%s".', $contrast['dimension'], $value['file_name'] ?? '');
                }
            }
        }

        foreach (['files_summary', 'setup_instruction', 'instructor_note'] as $field) {
            if (trim((string) ($plan[$field] ?? '')) === '') {
                $violations[] = sprintf('The practice plan needs a non-empty %s.', str_replace('_', ' ', $field));
            }
        }

        return [...$violations, ...(new InstructorNoteCoverageValidator)->violations((string) ($plan['instructor_note'] ?? ''), (array) ($plan['contrasts'] ?? []))];
    }

    private function key(string $item): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $item) ?? $item));
    }
}
