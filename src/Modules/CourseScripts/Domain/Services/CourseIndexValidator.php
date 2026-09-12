<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Services;

use Modules\CourseScripts\Domain\Exceptions\UnrecognisableIndexException;
use Modules\CourseScripts\Domain\ValueObjects\ParsedIndex;
use Modules\CourseScripts\Domain\ValueObjects\ParsedPoint;

/**
 * Decides whether a parse is an index at all (spec FR-7).
 *
 * The line it draws is deliberately narrow: it rejects only what is
 * *structurally impossible to generate from*. Everything merely thin — a
 * missing objective, no durations, no grouping — is the business of
 * {@see ParsedPoint::isThin()}, which flags rather than refuses (FR-5), because
 * research is what fills those gaps (FR-4a).
 *
 * **Absent groupings are explicitly not a rejection reason.** An earlier version
 * required them, which rejected every plain table of contents — exactly the
 * common case for an index on an arbitrary subject. The parser synthesises an
 * implicit group instead, so the only structural requirement left is: at least
 * one point.
 *
 * Getting the boundary wrong is expensive in both directions. Too strict and a
 * usable index is refused over one malformed entry the author could have fixed
 * in the UI. Too lax and an empty course reaches the run builder, where the
 * failure reads as a confusing "nothing to generate" rather than "this file is
 * not an index".
 */
final readonly class CourseIndexValidator
{
    public function __construct(
        private int $minPoints = 1,
        private int $maxPoints = 200,
        private int $maxGroups = 20,
    ) {}

    /**
     * @throws UnrecognisableIndexException
     */
    public function validate(ParsedIndex $index): void
    {
        $reasons = $this->reasons($index);

        if ($reasons !== []) {
            throw new UnrecognisableIndexException($reasons);
        }
    }

    /**
     * @return list<string>
     */
    public function reasons(ParsedIndex $index): array
    {
        $reasons = [];

        if ($index->points === []) {
            $reasons[] = 'No points were found. An index needs a list of topics, headings or table rows to script.';
        }

        if ($index->points !== [] && $index->pointCount() < $this->minPoints) {
            $reasons[] = sprintf('An index needs at least %d point(s).', $this->minPoints);
        }

        if ($index->pointCount() > $this->maxPoints) {
            $reasons[] = sprintf('This index declares %d points; the limit is %d.', $index->pointCount(), $this->maxPoints);
        }

        if ($index->groupCount() > $this->maxGroups) {
            $reasons[] = sprintf('This index declares %d groups; the limit is %d.', $index->groupCount(), $this->maxGroups);
        }

        $duplicates = $this->duplicatePositions($index);

        if ($duplicates !== []) {
            // Fatal rather than flagged: position is the ordering contract the
            // continuity chain hangs off (FR-17). Two points claiming position
            // 12 would silently corrupt what point 13 is told came before it.
            $reasons[] = 'Duplicate point positions: '.implode(', ', $duplicates).'.';
        }

        $orphans = $this->orphanGroups($index);

        if ($orphans !== []) {
            $reasons[] = 'Points reference groups that do not exist: '.implode(', ', $orphans).'.';
        }

        return $reasons;
    }

    /**
     * @return list<string>
     */
    private function duplicatePositions(ParsedIndex $index): array
    {
        $counts = [];

        foreach ($index->points as $point) {
            $counts[$point->position] = ($counts[$point->position] ?? 0) + 1;
        }

        return array_values(array_map(
            strval(...),
            array_keys(array_filter($counts, static fn (int $count): bool => $count > 1)),
        ));
    }

    /**
     * @return list<string>
     */
    private function orphanGroups(ParsedIndex $index): array
    {
        $groupNumbers = array_map(
            static fn ($group): int => $group->number,
            $index->groups,
        );

        $orphans = [];

        foreach ($index->points as $point) {
            if ($point->groupNumber === null) {
                continue;
            }

            if (! in_array($point->groupNumber, $groupNumbers, true)) {
                $orphans[$point->groupNumber] = (string) $point->groupNumber;
            }
        }

        return array_values($orphans);
    }

    /**
     * Numbering gaps are reported, never fatal: an index under construction may
     * legitimately hold points 1-10 and 20-30, and refusing it would block the
     * incremental authoring this module exists to support.
     *
     * @return list<int>
     */
    public function numberingGaps(ParsedIndex $index): array
    {
        if ($index->points === []) {
            return [];
        }

        $positions = array_map(static fn (ParsedPoint $point): int => $point->position, $index->points);
        sort($positions);

        $gaps = [];

        for ($expected = $positions[0]; $expected <= $positions[count($positions) - 1]; $expected++) {
            if (! in_array($expected, $positions, true)) {
                $gaps[] = $expected;
            }
        }

        return $gaps;
    }
}
