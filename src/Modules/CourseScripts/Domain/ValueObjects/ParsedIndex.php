<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * The result of parsing an uploaded index (spec FR-2, FR-2a).
 *
 * An index is **an ordered list of points on any subject** — Claude, Cursor,
 * Microsoft 365 Copilot, plumbing, anything. Grouping is optional: an index that
 * organises its points into blocks/modules/sections gets groups, one that is a
 * bare table of contents gets a single implicit group. Only the ordered points
 * are structurally required, because a point is the unit a script is written
 * for.
 *
 * Deliberately pure: no Eloquent, no file handle, no knowledge of the source
 * format. Markdown and PDF uploads both reduce to this same object, which is
 * what makes the spec's format-parity criterion a property of one code path.
 */
final readonly class ParsedIndex
{
    /**
     * @param  list<ParsedGroup>  $groups
     * @param  list<ParsedPoint>  $points
     */
    public function __construct(
        public ?string $title,
        public string $language,
        public ?int $declaredTotalMinutes,
        public array $groups,
        public array $points,
        /**
         * Free text that belongs to no point — a preamble, general guidance,
         * the author's notes about the whole course (FR-4c).
         */
        public ?string $courseNotes = null,
    ) {}

    /**
     * Nothing scriptable was recovered.
     *
     * **Points alone decide this — groups are not required.** An index with 30
     * points and no block headings is perfectly valid input; demanding a
     * grouping level would reject exactly the bare table of contents this
     * module is meant to accept (FR-2a, FR-7).
     */
    public function isEmpty(): bool
    {
        return $this->points === [];
    }

    /**
     * Points the parser recovered thinly — typically a title and nothing else.
     *
     * These are **flagged, never rejected** (FR-5). A thin point is the normal
     * case for a generic index, and research is what fills it in (FR-4a); the
     * flag exists so an author can choose to enrich it by hand first.
     *
     * @return list<ParsedPoint>
     */
    public function thinPoints(): array
    {
        return array_values(array_filter(
            $this->points,
            static fn (ParsedPoint $point): bool => $point->isThin(),
        ));
    }

    public function pointCount(): int
    {
        return count($this->points);
    }

    public function groupCount(): int
    {
        return count($this->groups);
    }

    /**
     * Sum of the points' declared durations, or null when the index states no
     * durations at all — which is common outside video courses and must not be
     * reported as a total of zero.
     */
    public function summedPointMinutes(): ?int
    {
        $declared = array_filter(
            array_map(static fn (ParsedPoint $point): int => $point->declaredDurationMinutes, $this->points),
            static fn (int $minutes): bool => $minutes > 0,
        );

        return $declared === [] ? null : array_sum($declared);
    }
}
