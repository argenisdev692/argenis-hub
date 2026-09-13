<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Rendering;

use Modules\CourseScripts\Domain\ValueObjects\PracticeDocument;
use Modules\CourseScripts\Domain\ValueObjects\ScriptDocument;

/**
 * Markdown deliverables, laid out like the author's reference files
 * (Guion_Video_39.md, Propuestas_Logistica_Heliantia.md). Deterministic.
 *
 * Model output is written as text: Markdown table pipes and line breaks inside
 * cells are escaped so a cell cannot break the table.
 */
final readonly class MarkdownDocumentRenderer
{
    #[\NoDiscard]
    public function script(ScriptDocument $document): string
    {
        $t = RenderVocabulary::for($document->language);
        $header = $document->technicalHeader;
        $lines = [];

        $lines[] = sprintf('# %s %d — %s', $t['script'], $document->videoNumber, $document->videoTitle);
        $lines[] = '';
        $lines[] = '## '.$t['technical'];
        $lines[] = '';
        $lines[] = sprintf('- **%s:** %d %s', $t['duration'], (int) $header['duration_minutes'], $t['minutes']);

        if (($header['block_number'] ?? null) !== null) {
            $lines[] = sprintf('- **%s:** %s – %s', $t['block'], $header['block_number'], $header['block_title']);
        }

        $lines[] = sprintf('- **%s:** %s', $t['format'], $header['recording_format'] ?? '');
        $lines[] = sprintf('- **%s:** %s', $t['video'], $header['position_label'] ?? '');
        $lines[] = '';

        if (! $document->isGrounded) {
            $lines[] = '> '.$t['ungrounded'];
            $lines[] = '';
        }

        if ($document->passedReview === false) {
            $lines[] = '> '.$t['not_passed'];
            $lines[] = '';
        }

        $lines[] = '## '.$t['objectives'];
        $lines[] = '';

        foreach ($document->learningObjectives as $objective) {
            $lines[] = '- '.$this->inline($objective);
        }

        $lines[] = '';
        $lines[] = sprintf('**%s:** %s', $t['continuity'], $this->inline($document->continuityNote));
        $lines[] = '';

        foreach ($document->sections as $section) {
            $isSub = ($section['parent_number'] ?? null) !== null;
            $minutes = (int) $section['minutes'];
            $title = sprintf('%s %s. %s', $isSub ? '###' : '##', $section['number'], $section['title']);

            if (! $isSub || $minutes > 0) {
                $title .= sprintf(' (%d %s)', $minutes, $minutes === 1 ? $t['minute'] : $t['minutes']);
            }

            if (($section['demo_label'] ?? null) !== null) {
                $title .= ' · '.$section['demo_label'];
            }

            $lines[] = $title;
            $lines[] = '';

            foreach ((array) ($section['segments'] ?? []) as $segment) {
                $lines = [...$lines, ...$this->segment($segment, $t), ''];
            }
        }

        $lines[] = '## '.$t['summary'];
        $lines[] = '';

        foreach ($document->summaryPoints as $point) {
            $lines[] = '- '.$this->inline($point);
        }

        $lines[] = '';

        if ($document->nextVideo !== null) {
            $lines[] = sprintf('**%s:** %s (%s %d)', $t['next_video'], $document->nextVideo['title'], $t['video'], $document->nextVideo['number']);

            if (trim((string) ($document->nextVideo['handoff'] ?? '')) !== '') {
                $lines[] = '';
                $lines[] = $this->inline((string) $document->nextVideo['handoff']);
            }

            $lines[] = '';
        }

        $notes = $document->recordingNotes;
        $lines[] = '## '.$t['recording_notes'];
        $lines[] = '';
        $lines = [...$lines, ...$this->list($t['preparation'], (array) ($notes['preparation'] ?? []))];
        $lines = [...$lines, ...$this->list($t['during_recording'], (array) ($notes['during_recording'] ?? []))];

        $tools = (array) ($notes['tools_required'] ?? []);
        $lines = [...$lines, ...($tools === []
            ? ['### '.$t['tools'], '', $this->inline((string) ($notes['tools_none_reason'] ?? '')), '']
            : $this->list($t['tools'], $tools))];

        if (trim((string) ($notes['continuity'] ?? '')) !== '') {
            $lines = [...$lines, '### '.$t['continuity_notes'], '', $this->inline((string) $notes['continuity']), ''];
        }

        $lines = [...$lines, ...$this->list($t['organisations'], (array) ($notes['organisations_used'] ?? []))];

        $lines[] = '## '.$t['checklist'];
        $lines[] = '';

        foreach ($document->verificationChecklist as $item) {
            $lines[] = '- [ ] '.$this->inline($item);
        }

        $lines[] = '';
        $lines[] = sprintf('*%s %d · %s %d.0 · %s*', $t['end'], $document->videoNumber, $t['version'], $document->version, $document->generatedOn);

        return implode("\n", $lines)."\n";
    }

    #[\NoDiscard]
    public function promptsSheet(ScriptDocument $document): string
    {
        $t = RenderVocabulary::for($document->language);
        $lines = [
            sprintf('# %s %d — %s', $t['prompts_title'], $document->videoNumber, $document->videoTitle),
            '',
            $t['prompts_intro'],
            '',
        ];

        foreach ($document->prompts() as $index => $prompt) {
            $heading = sprintf('## %d. %s %s — %s', $index + 1, $t['section'], $prompt['section_number'], $prompt['section_title']);

            if ($prompt['demo_label'] !== null) {
                $heading .= ' · '.$prompt['demo_label'];
            }

            $lines[] = $heading;
            $lines[] = '';

            if ($prompt['practice_files'] !== []) {
                $lines[] = sprintf('**%s:** %s', $t['paste_first'], implode(', ', $prompt['practice_files']));
                $lines[] = '';
            }

            $lines[] = '```text';
            $lines[] = str_replace('```', "'''", $prompt['prompt']);
            $lines[] = '```';
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    #[\NoDiscard]
    public function practice(PracticeDocument $document): string
    {
        $t = RenderVocabulary::for($document->language);
        $lines = [
            '# '.$document->headerTitle,
            '',
            sprintf('**%s:** %s', $t['files'], implode(', ', array_map(static fn (array $artifact): string => $artifact['file_name'].'.pdf', $document->artifacts))),
            '',
            $this->inline($document->setupInstruction),
            '',
        ];

        foreach ($document->usage as $usage) {
            $lines[] = sprintf('- **%s:** %s (%s %s) — %s%s', $t['use_in'], $usage['demo_label'], $t['section'], $usage['section_number'], $this->inline($usage['purpose']), isset($usage['file_name']) ? ' · '.$usage['file_name'] : '');
        }

        $lines[] = '';
        $lines[] = sprintf('**%s:** %s', $t['instructor_note'], $this->inline($document->instructorNote));
        $lines[] = '';

        if ($document->designedContrasts !== []) {
            $files = array_map(static fn (array $artifact): string => (string) $artifact['file_name'], $document->artifacts);
            $lines[] = '### '.$t['contrasts'];
            $lines[] = '';
            $lines[] = '| '.implode(' | ', array_map($this->cell(...), [$t['dimension'], ...$files, $t['intended_effect']])).' |';
            $lines[] = '|'.str_repeat(' --- |', count($files) + 2);

            foreach ($document->designedContrasts as $contrast) {
                $values = [];

                foreach ($files as $file) {
                    $values[] = (string) (array_find((array) $contrast['values'], static fn (array $value): bool => $value['file_name'] === $file)['value'] ?? '—');
                }

                $lines[] = '| '.implode(' | ', array_map($this->cell(...), [(string) $contrast['dimension'], ...$values, (string) $contrast['intended_effect']])).' |';
            }

            $lines[] = '';
        }

        $lines[] = '> '.$t['fictional'];
        $lines[] = '';

        foreach ($document->artifacts as $artifact) {
            $lines[] = '---';
            $lines[] = '';
            $lines = [...$lines, ...$this->blocks((array) $artifact['content_blocks'], baseLevel: 2)];
        }

        return implode("\n", $lines)."\n";
    }

    #[\NoDiscard]
    public function practiceFile(PracticeDocument $document, string $fileName): string
    {
        $artifact = $document->artifact($fileName) ?? ['content_blocks' => []];

        return implode("\n", $this->blocks((array) $artifact['content_blocks'], baseLevel: 1))."\n";
    }

    /**
     * @param  array<string, mixed>  $segment
     * @param  array<string, string>  $t
     * @return list<string>
     */
    private function segment(array $segment, array $t): array
    {
        $type = (string) ($segment['type'] ?? 'narration');
        $text = $this->inline((string) ($segment['text'] ?? ''));
        $file = (string) ($segment['practice_file'] ?? '');

        return match ($type) {
            'on_screen_prompt' => ['**'.$t['prompt'].':**', '', '```text', str_replace('```', "'''", (string) $segment['prompt']), '```'],
            'expected_result' => ['**'.$t['expected_result'].':** '.$text],
            'on_screen_actions' => ['**'.$t['actions'].':**', '', ...array_map(fn (string $item): string => '- '.$this->inline($item), (array) $segment['items'])],
            'show_on_screen' => array_values(array_filter([
                '**'.$t['show'].((bool) ($segment['read_aloud'] ?? false) ? ' ('.$t['read_aloud'].')' : '').':**',
                $file !== '' ? '*'.$t['practice_file'].': '.$file.'*' : null,
                $text,
            ], static fn (?string $line): bool => $line !== null && $line !== '')),
            'on_screen_table' => ['**'.$t['table'].':**', '', ...$this->table((array) $segment['table_columns'], (array) $segment['table_rows'])],
            'presenter_note' => ['*'.$t['note'].':* '.implode(' · ', array_map($this->inline(...), [...(array) ($segment['items'] ?? []), ...($text === '' ? [] : [$text])]))],
            default => [$text],
        };
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return list<string>
     */
    private function blocks(array $blocks, int $baseLevel): array
    {
        $lines = [];

        foreach ($blocks as $block) {
            $lines = [...$lines, ...match ((string) ($block['type'] ?? 'paragraph')) {
                'heading' => [str_repeat('#', min(6, $baseLevel + max(1, (int) $block['level']) - 1)).' '.$this->inline((string) $block['text'])],
                'list' => array_map(fn (string $item): string => '- '.$this->inline($item), (array) $block['items']),
                'table' => $this->table((array) $block['table_header'], (array) $block['table_rows'], (bool) ($block['total_row'] ?? false)),
                'key_values' => [implode(' · ', array_map(fn (array $pair): string => '**'.$this->inline((string) $pair['key']).':** '.$this->inline((string) $pair['value']), (array) $block['pairs']))],
                'footer' => ['*'.$this->inline((string) $block['text']).'*'],
                default => [$this->inline((string) ($block['text'] ?? ''))],
            }, ''];
        }

        return $lines;
    }

    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     * @return list<string>
     */
    private function table(array $header, array $rows, bool $boldLastRow = false): array
    {
        $columns = max(count($header), ...array_map(static fn (array $row): int => count($row), $rows ?: [[]]));

        if ($columns === 0) {
            return [];
        }

        $pad = static fn (array $cells): array => array_pad(array_slice($cells, 0, $columns), $columns, '');
        $lines = [
            '| '.implode(' | ', array_map($this->cell(...), $pad($header))).' |',
            '|'.str_repeat(' --- |', $columns),
        ];

        foreach ($rows as $index => $row) {
            $cells = array_map($this->cell(...), $pad(array_map(strval(...), $row)));

            if ($boldLastRow && $index === count($rows) - 1) {
                $cells = array_map(static fn (string $cell): string => $cell === '' ? '' : '**'.$cell.'**', $cells);
            }

            $lines[] = '| '.implode(' | ', $cells).' |';
        }

        return $lines;
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function list(string $title, array $items): array
    {
        if ($items === []) {
            return [];
        }

        return ['### '.$title, '', ...array_map(fn (string $item): string => '- '.$this->inline($item), $items), ''];
    }

    private function cell(string $value): string
    {
        return str_replace(['|', "\r", "\n"], ['\|', ' ', ' '], trim($value));
    }

    private function inline(string $value): string
    {
        return trim(str_replace("\r", '', $value));
    }
}
