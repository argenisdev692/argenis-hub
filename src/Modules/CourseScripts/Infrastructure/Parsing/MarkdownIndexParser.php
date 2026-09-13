<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Parsing;

use Modules\CourseScripts\Domain\Ports\IndexDocumentParserPort;
use Modules\CourseScripts\Domain\ValueObjects\ParsedGroup;
use Modules\CourseScripts\Domain\ValueObjects\ParsedIndex;
use Modules\CourseScripts\Domain\ValueObjects\ParsedPoint;

/**
 * Recovers an ordered list of points from an index on **any subject**
 * (spec FR-2, FR-2a).
 *
 * Two design rules, both learned the hard way:
 *
 * **1. Parse semantic anchors, not Markdown syntax.** Measured against the real
 * corpus, a PDF exported from a Markdown source keeps `###`, `|` and `**`
 * intact, while a typeset PDF keeps only the words. Keying on `###` would have
 * worked for the first and silently failed for the second, so Markdown markers
 * are treated as optional decoration throughout. That is what lets
 * {@see PdfIndexParser} delegate here rather than carry a second grammar.
 *
 * **2. Never require one author's vocabulary or structure.** An earlier version
 * demanded `BLOQUE n` headings and `VÍDEO n` labels, which meant an index for
 * Cursor or Microsoft 365 Copilot — a plain table of contents in English with no
 * blocks — parsed to nothing and was rejected as "not an index". It was not: it
 * was simply written differently.
 *
 * So extraction is a **ladder of strategies**, most explicit first, and the
 * first rung that yields points wins:
 *
 *   1. keyword-labelled points  — `VÍDEO 3 - Title`, `Lesson 3: Title`
 *   2. table-of-contents rows   — `| 3 | Title | Topic | 9 min |`
 *   3. numbered headings        — `## 3. Title`
 *   4. plain headings           — `## Title` at the repeating level
 *   5. numbered list items      — `3. Title`
 *   6. bullet list items        — `- Title`
 *
 * Grouping is optional at every rung: an index without one gets a single
 * implicit group, so downstream code never branches on the author's choices.
 * The per-point brief is optional too — where absent, research supplies the
 * substance (FR-4a), which is why a title-only point is valid input rather than
 * an error.
 */
final readonly class MarkdownIndexParser implements IndexDocumentParserPort
{
    /** @var list<string> */
    private const array SUPPORTED_MIME_TYPES = [
        'text/markdown',
        'text/x-markdown',
        'text/plain',
    ];

    /**
     * Dash variants used interchangeably as the "number – title" separator.
     * Normalised to one form so a single pattern covers en dash, em dash,
     * figure dash and hyphen.
     */
    private const string DASHES = '–—‒-';

    /**
     * How many points a rung must produce before it is believed.
     *
     * The threshold is **per rung, by how ambiguous that rung is**, not global.
     * An explicit `VÍDEO 1 - Title` or a numbered table row is an unambiguous
     * declaration and is believed on its own. A lone `## Heading` or a single
     * `- bullet` is not: every document has those, so one of them is noise and
     * only a repetition of them is structure.
     *
     * A flat threshold of 2 got this wrong in the other direction — it rejected
     * a legitimate one-point index that said so explicitly.
     */
    private const int MINIMUM_EXPLICIT_POINTS = 1;

    private const int MINIMUM_INFERRED_POINTS = 2;

    public function supports(string $mimeType): bool
    {
        return in_array(strtolower($mimeType), self::SUPPORTED_MIME_TYPES, true);
    }

    public function parse(string $absolutePath, string $mimeType): ParsedIndex
    {
        $contents = @file_get_contents($absolutePath);

        return $this->parseText($contents === false ? '' : $contents);
    }

    /**
     * The real entry point, also called by {@see PdfIndexParser} with extracted
     * text. Public so the PDF path reuses this grammar verbatim.
     */
    public function parseText(string $raw): ParsedIndex
    {
        $text = $this->normalise($raw);
        $lines = explode("\n", $text);

        $groups = $this->extractGroups($lines);
        $firstPointLine = null;
        $points = $this->extractPoints($text, $lines, $groups, $firstPointLine);

        // A point always belongs to a group, even when the author used none.
        if ($points !== [] && $groups === []) {
            $groups = [ParsedGroup::implicit()];
            $points = array_map(static fn (ParsedPoint $p): ParsedPoint => $p->inGroup(1), $points);
        }

        $title = $this->extractTitle($lines);

        return new ParsedIndex(
            title: $title,
            language: $this->detectLanguage($text),
            declaredTotalMinutes: $this->extractTotalMinutes($text),
            groups: $groups,
            points: $points,
            courseNotes: $points === [] ? null : $this->extractCourseNotes($lines, $firstPointLine ?? count($lines), $title),
        );
    }

    /**
     * Collapses the differences between a Markdown file and text lifted from a
     * PDF: line endings, the non-breaking and zero-width spaces PDF extraction
     * introduces, dash variants, and runs of whitespace.
     *
     * Emphasis markers are stripped rather than matched around, so
     * `**Objetivo:**` and a typeset `Objetivo:` reach the field matcher
     * identically.
     */
    private function normalise(string $raw): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        $text = str_replace(["\u{00A0}", "\u{202F}", "\u{200B}"], [' ', ' ', ''], $text);
        $text = str_replace(['**', '__'], '', $text);
        $text = preg_replace('/['.self::DASHES.']/u', '-', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        return preg_replace('/[ \t]+\n/u', "\n", $text) ?? $text;
    }

    /**
     * @param  list<string>  $lines
     */
    private function extractTitle(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/^#\s+(.+)$/u', trim($line), $m) === 1) {
                return trim($m[1]);
            }
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line !== '' && ! str_starts_with($line, '-') && ! str_starts_with($line, '|')) {
                return mb_substr(ltrim($line, '# '), 0, 255);
            }
        }

        return null;
    }

    private function extractTotalMinutes(string $text): ?int
    {
        $patterns = [
            '/Duraci[óo]n\s+total:?\s*(\d+)\s*(?:minutos?|min)\b/iu',
            '/Total\s+(?:duration|length):?\s*(\d+)\s*(?:minutes?|min)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * Groupings, when the index has them. Any of the vocabulary's group words
     * counts, in any language it knows.
     *
     * @param  list<string>  $lines
     * @return list<ParsedGroup>
     */
    private function extractGroups(array $lines): array
    {
        $keywords = IndexVocabulary::groupPattern();
        $pattern = '/^#{0,6}\s*(?:'.$keywords.')\s*(\d+)\s*[-:.]?\s*(.*?)\s*(?:\((\d+)\s*min[^)]*\))?\s*$/iu';

        $groups = [];
        $seen = [];
        $position = 0;

        foreach ($lines as $line) {
            if (preg_match($pattern, trim($line), $m) !== 1) {
                continue;
            }

            $number = (int) $m[1];

            // Indexes repeat a group heading (contents, then detail). First wins.
            if (isset($seen[$number])) {
                continue;
            }

            $seen[$number] = true;

            $groups[] = new ParsedGroup(
                number: $number,
                title: trim($m[2]) !== '' ? trim($m[2]) : 'Group '.$number,
                declaredDurationMinutes: isset($m[3]) && $m[3] !== '' ? (int) $m[3] : null,
                position: $position++,
            );
        }

        usort($groups, static fn (ParsedGroup $a, ParsedGroup $b): int => $a->number <=> $b->number);

        return array_values($groups);
    }

    /**
     * The strategy ladder. The first rung producing at least
     * the rung.s minimum (see the constants above) wins, and its results are then enriched
     * with whatever table-of-contents metadata and per-point brief the index
     * also contains.
     *
     * `$firstPointLine` receives the line index where the first point starts,
     * so everything above it can be read as course-level notes (FR-4c).
     *
     * @param  list<string>  $lines
     * @param  list<ParsedGroup>  $groups
     * @return list<ParsedPoint>
     */
    private function extractPoints(string $text, array $lines, array $groups, ?int &$firstPointLine): array
    {
        $tocRows = $this->extractTocRows($lines, $groups);

        $candidates = $this->labelledPoints($text)
            ?: $this->tocPoints($tocRows)
            ?: $this->headingPoints($lines)
            ?: $this->listPoints($lines);

        if ($candidates === []) {
            return [];
        }

        $details = $this->extractDetailBodies($text);
        $firstPointLine = $this->firstPointLine($lines, $candidates, $details, $tocRows);

        $points = [];

        foreach ($candidates as $position => $candidate) {
            $number = $candidate['number'] ?? $position + 1;
            $toc = $tocRows[$number] ?? null;
            $body = $details[$number] ?? $candidate['body'] ?? '';

            $points[] = new ParsedPoint(
                position: $number,
                title: mb_substr($candidate['title'], 0, 255),
                groupNumber: $candidate['group'] ?? $toc['group'] ?? $this->inferGroup($number, $tocRows, $groups),
                topic: $toc['topic'] ?? null,
                declaredDurationMinutes: $toc['minutes'] ?? $candidate['minutes'] ?? 0,
                objective: $this->briefField($body, 'objective'),
                learningAreas: $this->briefList($body, 'learningAreas'),
                audienceObjectives: $this->briefList($body, 'studentObjectives'),
                mandatoryContent: $this->briefList($body, 'mandatoryContent'),
                errorsToAvoid: $this->briefList($body, 'errorsToAvoid'),
                expectedResult: $this->briefField($body, 'expectedResult'),
                notes: $this->notesResidue($body),
            );
        }

        usort($points, static fn (ParsedPoint $a, ParsedPoint $b): int => $a->position <=> $b->position);

        return array_values($points);
    }

    /**
     * Rung 1 — points introduced by one of the vocabulary's point words.
     *
     * @return list<array{number: int, title: string, body?: string}>
     */
    private function labelledPoints(string $text): array
    {
        $keywords = IndexVocabulary::pointPattern();
        $pattern = '/^#{0,6}\s*(?:'.$keywords.')\s+(\d+)\s*[-:.]\s*(.+?)\s*$/imu';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $points = [];

        foreach ($matches as $match) {
            $number = (int) $match[1];
            $title = trim($match[2]);

            // The richer occurrence wins when a heading appears twice.
            if (isset($points[$number]) && mb_strlen($points[$number]['title']) >= mb_strlen($title)) {
                continue;
            }

            $points[$number] = ['number' => $number, 'title' => $title];
        }

        ksort($points);

        return count($points) >= self::MINIMUM_EXPLICIT_POINTS ? array_values($points) : [];
    }

    /**
     * Rung 2 — the table of contents is the only structure present.
     *
     * @param  array<int, array{topic: ?string, minutes: ?int, group: ?int, title: ?string}>  $tocRows
     * @return list<array{number: int, title: string, minutes: ?int, group: ?int}>
     */
    private function tocPoints(array $tocRows): array
    {
        $points = [];

        foreach ($tocRows as $number => $row) {
            if (($row['title'] ?? '') === '') {
                continue;
            }

            $points[] = [
                'number' => $number,
                'title' => (string) $row['title'],
                'minutes' => $row['minutes'],
                'group' => $row['group'],
            ];
        }

        return count($points) >= self::MINIMUM_EXPLICIT_POINTS ? $points : [];
    }

    /**
     * Rung 3 and 4 — headings. Numbered headings keep their own numbering;
     * plain headings are numbered by their order of appearance.
     *
     * The chosen level is the deepest one that repeats, because that is where a
     * table of contents puts its items — shallower levels are the document's
     * own title and its groupings.
     *
     * @param  list<string>  $lines
     * @return list<array{number: int, title: string}>
     */
    private function headingPoints(array $lines): array
    {
        $byLevel = [];
        $headingLines = [];

        foreach ($lines as $lineIndex => $line) {
            if (preg_match('/^(#{1,6})\s+(.+?)\s*$/u', trim($line), $m) !== 1) {
                continue;
            }

            $headingLines[] = $lineIndex;

            if (strlen($m[1]) >= 2) {
                $byLevel[strlen($m[1])][] = ['title' => trim($m[2]), 'line' => $lineIndex];
            }
        }

        krsort($byLevel);

        foreach ($byLevel as $headings) {
            if (count($headings) < self::MINIMUM_INFERRED_POINTS) {
                continue;
            }

            $points = [];

            foreach ($headings as $index => $heading) {
                if ($this->isGroupHeading($heading['title'])) {
                    continue;
                }

                $body = $this->linesUntilNextHeading($lines, $heading['line'], $headingLines);

                // "3. Multi-file edits" keeps the author's number; a plain
                // heading is numbered by appearance.
                if (preg_match('/^(\d+)\s*[-.):]\s*(.+)$/u', $heading['title'], $m) === 1) {
                    $points[] = ['number' => (int) $m[1], 'title' => trim($m[2]), 'body' => $body, 'line' => $heading['line']];

                    continue;
                }

                $points[] = ['number' => $index + 1, 'title' => $heading['title'], 'body' => $body, 'line' => $heading['line']];
            }

            if (count($points) >= self::MINIMUM_INFERRED_POINTS) {
                return $points;
            }
        }

        return [];
    }

    /**
     * Rung 5 and 6 — a bare list. The last resort before giving up, and the
     * shape of the simplest possible index someone can paste in.
     *
     * @param  list<string>  $lines
     * @return list<array{number: int, title: string}>
     */
    private function listPoints(array $lines): array
    {
        $numbered = [];
        $bulleted = [];

        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);

            if (preg_match('/^(\d+)\s*[-.):]\s+(.{3,})$/u', $line, $m) === 1) {
                $numbered[] = ['number' => (int) $m[1], 'title' => trim($m[2]), 'line' => $lineIndex];

                continue;
            }

            if (preg_match('/^[-*•]\s+(.{3,})$/u', $line, $m) === 1 && ! $this->isGroupHeading($m[1])) {
                $bulleted[] = ['number' => count($bulleted) + 1, 'title' => trim($m[1]), 'line' => $lineIndex];
            }
        }

        if (count($numbered) >= self::MINIMUM_INFERRED_POINTS) {
            return $numbered;
        }

        return count($bulleted) >= self::MINIMUM_INFERRED_POINTS ? $bulleted : [];
    }

    private function isGroupHeading(string $heading): bool
    {
        return preg_match('/^(?:'.IndexVocabulary::groupPattern().')\b/iu', $heading) === 1;
    }

    /**
     * Table rows, which are where an index states a point's topic and duration.
     *
     * Both the piped Markdown form and the de-piped form a typeset PDF leaves
     * behind are accepted; the latter is anchored on a trailing "N min" because
     * without pipes that is the only reliable field boundary left.
     *
     * @param  list<string>  $lines
     * @param  list<ParsedGroup>  $groups
     * @return array<int, array{topic: ?string, minutes: ?int, group: ?int, title: ?string}>
     */
    private function extractTocRows(array $lines, array $groups): array
    {
        $rows = [];
        $currentGroup = null;
        $groupPattern = '/^#{0,6}\s*(?:'.IndexVocabulary::groupPattern().')\s+(\d+)\b/iu';
        $pointPattern = '/^#{0,6}\s*(?:'.IndexVocabulary::pointPattern().')\s+\d+\s*[-:.]/iu';

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match($groupPattern, $line, $m) === 1) {
                $currentGroup = (int) $m[1];

                continue;
            }

            // Once per-point detail begins, table rows are over.
            if (preg_match($pointPattern, $line) === 1) {
                $currentGroup = null;

                continue;
            }

            $row = $this->matchTocRow($line);

            if ($row === null) {
                continue;
            }

            $rows[$row['number']] = [
                'title' => $row['title'],
                'topic' => $row['topic'],
                'minutes' => $row['minutes'],
                'group' => $currentGroup,
            ];
        }

        return $rows;
    }

    /**
     * @return array{number: int, title: ?string, topic: ?string, minutes: ?int}|null
     */
    private function matchTocRow(string $line): ?array
    {
        if (str_contains($line, '|')) {
            $cells = array_map(trim(...), explode('|', trim($line, "| \t")));
            $cells = array_values(array_filter($cells, static fn (string $c): bool => $c !== ''));

            // A separator row (|---|---|) carries no data.
            if ($cells === [] || preg_match('/^:?-{2,}:?$/', $cells[0]) === 1) {
                return null;
            }

            if (count($cells) < 2 || preg_match('/^\d+$/', $cells[0]) !== 1) {
                return null;
            }

            $minutes = null;
            $last = $cells[count($cells) - 1];

            if (preg_match('/^(\d+)\s*min/iu', $last, $m) === 1) {
                $minutes = (int) $m[1];
                array_pop($cells);
            }

            return [
                'number' => (int) $cells[0],
                'title' => $cells[1] ?? null,
                'topic' => $cells[2] ?? null,
                'minutes' => $minutes,
            ];
        }

        if (preg_match('/^(\d+)\s+(.+?)\s+(\d+)\s*min\b/iu', $line, $m) === 1) {
            return [
                'number' => (int) $m[1],
                'title' => trim($m[2]),
                'topic' => null,
                'minutes' => (int) $m[3],
            ];
        }

        return null;
    }

    /**
     * The body of text following each point's heading, where a rich index puts
     * its per-point brief. Absent for a bare table of contents, which is fine.
     *
     * @return array<int, string>
     */
    private function extractDetailBodies(string $text): array
    {
        $pattern = '/^#{0,6}\s*(?:'.IndexVocabulary::pointPattern().')\s+(\d+)\s*[-:.]\s*.+?$/imu';

        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $bodies = [];
        $count = count($matches);

        foreach ($matches as $i => $match) {
            $number = (int) $match[1][0];
            $start = $match[0][1] + strlen($match[0][0]);
            $end = $i + 1 < $count ? $matches[$i + 1][0][1] : strlen($text);
            $body = substr($text, $start, $end - $start);

            if (isset($bodies[$number]) && strlen($bodies[$number]) > strlen($body)) {
                continue;
            }

            $bodies[$number] = $body;
        }

        return $bodies;
    }

    /**
     * @param  array<int, array{topic: ?string, minutes: ?int, group: ?int, title: ?string}>  $tocRows
     * @param  list<ParsedGroup>  $groups
     */
    private function inferGroup(int $number, array $tocRows, array $groups): ?int
    {
        for ($candidate = $number - 1; $candidate >= 1; $candidate--) {
            if (isset($tocRows[$candidate]) && $tocRows[$candidate]['group'] !== null) {
                return $tocRows[$candidate]['group'];
            }
        }

        return $groups[0]->number ?? null;
    }

    /**
     * A single-line brief field, in any of the languages the vocabulary knows.
     *
     * Terminated by the next known label, a horizontal rule, or the end of the
     * body — never by a blank line, because PDF extraction drops those
     * unpredictably and relying on them made the Markdown and PDF paths
     * disagree on 46 of 48 points.
     */
    private function briefField(string $body, string $field): ?string
    {
        return $this->matchBriefField($body, $field)['value'] ?? null;
    }

    /**
     * The value of a single-line field and the exact text it consumed, so the
     * remainder of the body can be kept as the author's notes (FR-4b).
     *
     * A blank line followed by a line that does not start in lowercase also
     * ends the value: that is a new paragraph (typically the author's notes),
     * whereas a PDF's stray blank line inside a wrapped sentence is followed by
     * its lowercase continuation.
     *
     * @return array{value: string, consumed: string}|null
     */
    private function matchBriefField(string $body, string $field): ?array
    {
        if ($body === '') {
            return null;
        }

        $terminators = $this->briefTerminators();

        foreach (IndexVocabulary::labelsFor($field) as $label) {
            $pattern = '/(?:^|\n)\s*'.$label.'\s*:\s*(.+?)(?=\n\s*(?:'.$terminators.')|\n\s*-{3,}|\n[ \t]*\n(?=[ \t]*[^\p{Ll}\s])|\z)/isu';

            if (preg_match($pattern, $body, $m) !== 1) {
                continue;
            }

            $keptLines = $this->linesOfOneParagraph($m[1]);
            $keptText = implode("\n", $keptLines);

            $value = preg_replace('/\s*\n\s*/u', ' ', trim($keptText)) ?? trim($keptText);
            $value = preg_replace('/\s*-{3,}\s*$/u', '', trim($value)) ?? $value;
            $value = trim($value);

            if ($value !== '') {
                $labelPart = substr($m[0], 0, strlen($m[0]) - strlen($m[1]));

                return ['value' => $value, 'consumed' => $labelPart.$keptText];
            }
        }

        return null;
    }

    /**
     * The leading lines of a captured value that still belong to it.
     *
     * PDF extraction drops blank lines, so paragraph ends must be inferred: a
     * line that closes a sentence followed by one that opens a new sentence is
     * a boundary; a line that does not close a sentence, or a lowercase
     * continuation, is the same wrapped value.
     *
     * @return list<string>
     */
    private function linesOfOneParagraph(string $captured): array
    {
        $lines = explode("\n", $captured);
        $kept = [array_shift($lines)];

        foreach ($lines as $line) {
            $previous = rtrim((string) end($kept));

            if ($this->startsNewSentence($previous, $line)) {
                break;
            }

            $kept[] = $line;
        }

        return $kept;
    }

    private function startsNewSentence(string $previousLine, string $line): bool
    {
        $trimmed = trim($line);

        return $trimmed !== ''
            && preg_match('/[.!?]$/u', $previousLine) === 1
            && preg_match('/^\p{Ll}/u', $trimmed) !== 1
            && preg_match('/^(?:[-*•]|\d+[.)])\s/u', $trimmed) !== 1;
    }

    /**
     * A label only terminates a value when it really is a label — followed by
     * a colon or the end of its line. Otherwise a wrapped PDF line that merely
     * starts with the word ("resultado desde el minuto uno.") would cut the
     * previous field short.
     */
    private function briefTerminators(): string
    {
        return '(?:'.IndexVocabulary::allBriefLabelsPattern().')(?=[ \t]*(?::|\n|\z))'
            .'|(?:'.IndexVocabulary::pointPattern().')\s+\d+'
            .'|(?:'.IndexVocabulary::groupPattern().')\s+\d+';
    }

    /**
     * A labelled list, accepting bulleted and numbered forms alike — indexes use
     * bullets for some fields and numbers for others, often in the same file.
     *
     * @return list<string>
     */
    private function briefList(string $body, string $field): array
    {
        return $this->matchBriefList($body, $field)['items'] ?? [];
    }

    /**
     * A labelled list and the exact text it consumed (see {@see matchBriefField}).
     *
     * @return array{items: list<string>, consumed: string}|null
     */
    private function matchBriefList(string $body, string $field): ?array
    {
        if ($body === '') {
            return null;
        }

        $terminators = $this->briefTerminators();

        foreach (IndexVocabulary::labelsFor($field) as $label) {
            $pattern = '/(?:^|\n)\s*'.$label.'\s*:?\s*\n(.*?)(?=\n\s*(?:'.$terminators.')|\z)/isu';

            if (preg_match($pattern, $body, $m) !== 1) {
                continue;
            }

            ['items' => $items, 'consumedLines' => $consumedLines] = $this->parseListItems($m[1]);

            if ($items !== []) {
                $labelPart = substr($m[0], 0, strlen($m[0]) - strlen($m[1]));

                return ['items' => $items, 'consumed' => $labelPart.implode("\n", $consumedLines)];
            }
        }

        return null;
    }

    /**
     * Items of a list block. The list ends at a blank line followed by a line
     * that is neither an item nor a lowercase continuation — that is a new
     * paragraph, usually the author's notes, and must not be glued onto the
     * last item.
     *
     * @return array{items: list<string>, consumedLines: list<string>}
     */
    private function parseListItems(string $block): array
    {
        $items = [];
        $consumed = [];
        $current = null;
        $afterBlank = false;

        foreach (explode("\n", $block) as $rawLine) {
            $line = trim($rawLine);

            if ($line === '' || preg_match('/^-{3,}$/', $line) === 1) {
                $afterBlank = $afterBlank || $line === '';
                $consumed[] = $rawLine;

                continue;
            }

            if (preg_match('/^(?:[-*•]|\d+[.)])\s+(.*)$/u', $line, $m) === 1) {
                if ($current !== null) {
                    $items[] = $current;
                }

                $current = trim($m[1]);
                $afterBlank = false;
                $consumed[] = $rawLine;

                continue;
            }

            if ($current !== null && (
                ($afterBlank && preg_match('/^\p{Ll}/u', $line) !== 1)
                || $this->startsNewSentence($current, $line)
            )) {
                break;
            }

            // A wrapped continuation of the previous item, routine in PDF text.
            if ($current !== null) {
                $current .= ' '.$line;
                $consumed[] = $rawLine;
            }
        }

        if ($current !== null) {
            $items[] = $current;
        }

        return [
            'items' => array_values(array_filter(
                array_map(trim(...), $items),
                static fn (string $item): bool => $item !== '',
            )),
            'consumedLines' => $consumed,
        ];
    }

    /**
     * What remains of a point's body once every recognised brief field is
     * removed: the author's own notes for that point (FR-4b).
     */
    private function notesResidue(string $body): ?string
    {
        if (trim($body) === '') {
            return null;
        }

        $residue = $body;

        foreach (array_keys(IndexVocabulary::BRIEF_LABELS) as $field) {
            $match = in_array($field, ['objective', 'expectedResult'], true)
                ? $this->matchBriefField($residue, $field)
                : $this->matchBriefList($residue, $field);

            if ($match === null) {
                continue;
            }

            $position = strpos($residue, $match['consumed']);

            if ($position !== false) {
                $residue = substr_replace($residue, "\n", $position, strlen($match['consumed']));
            }
        }

        $labelOnly = '/^(?:'.IndexVocabulary::allBriefLabelsPattern().')\s*:?$/iu';
        $kept = [];

        foreach (explode("\n", $residue) as $line) {
            $trimmed = trim($line);

            $kept[] = preg_match('/^-{3,}$/', $trimmed) === 1 || preg_match($labelOnly, $trimmed) === 1
                ? ''
                : $trimmed;
        }

        return $this->collapseParagraphs($kept);
    }

    /**
     * Text above the first point that is not the title, a total duration, a
     * grouping, a table of contents or a heading (FR-4c).
     *
     * @param  list<string>  $lines
     */
    private function extractCourseNotes(array $lines, int $firstPointLine, ?string $title): ?string
    {
        $groupPattern = '/^#{0,6}\s*(?:'.IndexVocabulary::groupPattern().')\s*\d+/iu';
        $kept = [];

        foreach (array_slice($lines, 0, $firstPointLine) as $line) {
            $trimmed = trim($line);

            $isStructure = $trimmed === ''
                || ($title !== null && ltrim($trimmed, '# ') === $title)
                || preg_match('/^#{1,6}\s/u', $trimmed) === 1
                || str_starts_with($trimmed, '|')
                || preg_match('/^-{3,}$/', $trimmed) === 1
                || preg_match($groupPattern, $trimmed) === 1
                || $this->extractTotalMinutes($trimmed) !== null
                || $this->matchTocRow($trimmed) !== null;

            $kept[] = $isStructure ? '' : $trimmed;
        }

        return $this->collapseParagraphs($kept);
    }

    /**
     * @param  list<string>  $lines
     */
    private function collapseParagraphs(array $lines): ?string
    {
        $text = trim(implode("\n", $lines));
        $text = preg_replace("/\n{3,}/u", "\n\n", $text) ?? $text;

        return $text === '' ? null : $text;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<int>  $headingLines
     */
    private function linesUntilNextHeading(array $lines, int $headingLine, array $headingLines): string
    {
        $next = count($lines);

        foreach ($headingLines as $candidate) {
            if ($candidate > $headingLine) {
                $next = $candidate;

                break;
            }
        }

        return implode("\n", array_slice($lines, $headingLine + 1, $next - $headingLine - 1));
    }

    /**
     * Where the points begin: the first candidate's line when the rung knows
     * it, otherwise the first labelled heading or table-of-contents row.
     *
     * @param  list<string>  $lines
     * @param  list<array<string, mixed>>  $candidates
     * @param  array<int, string>  $details
     * @param  array<int, array<string, mixed>>  $tocRows
     */
    private function firstPointLine(array $lines, array $candidates, array $details, array $tocRows): int
    {
        $candidateLines = [];

        foreach ($candidates as $candidate) {
            if (isset($candidate['line'])) {
                $candidateLines[] = (int) $candidate['line'];
            }
        }

        if ($candidateLines !== []) {
            return min($candidateLines);
        }

        $pattern = '/^#{0,6}\s*(?:'.IndexVocabulary::pointPattern().')\s+\d+\s*[-:.]/iu';

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);

            if (preg_match($pattern, $trimmed) === 1) {
                return $index;
            }

            if ($details === [] && $tocRows !== [] && $this->matchTocRow($trimmed) !== null) {
                return $index;
            }
        }

        return count($lines);
    }

    /**
     * Cheap stopword frequency, not a library: the only consumer is a
     * `language` column telling the writer which language to write in, and a
     * wrong guess is visible and correctable in one edit.
     */
    private function detectLanguage(string $text): string
    {
        $sample = mb_strtolower(mb_substr($text, 0, 20000));

        $markers = [
            'es' => [' el ', ' la ', ' de ', ' que ', ' con ', ' para ', ' del '],
            'en' => [' the ', ' and ', ' of ', ' that ', ' with ', ' for ', ' this '],
            'pt' => [' o ', ' as ', ' de ', ' que ', ' com ', ' para ', ' dos '],
        ];

        $scores = [];

        foreach ($markers as $language => $words) {
            $scores[$language] = array_sum(array_map(
                static fn (string $word): int => substr_count($sample, $word),
                $words,
            ));
        }

        arsort($scores);
        $best = array_key_first($scores);

        return $scores[$best] > 0 ? (string) $best : 'es';
    }
}
