<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\ValueObjects;

/**
 * One point of an index — the unit a script gets written for (spec FR-3, FR-4).
 *
 * **Only `position` and `title` are required.** Everything else is enrichment
 * that a rich index happens to supply and a bare table of contents does not.
 * That asymmetry is the whole point of the type: an index for a Cursor course
 * may give nothing but "12. Multi-file edits with Composer", and that is valid,
 * scriptable input. The substance a script needs is supplied by research
 * (FR-4a), not demanded from the author up front.
 *
 * This is the durable input to generation: every later stage — research,
 * outline, sections, review — reads the persisted form of this object, never
 * the original file, so an author's correction is honoured by every later run.
 */
final readonly class ParsedPoint
{
    /**
     * @param  list<string>  $learningAreas
     * @param  list<string>  $audienceObjectives
     * @param  list<string>  $mandatoryContent
     * @param  list<string>  $errorsToAvoid
     */
    public function __construct(
        public int $position,
        public string $title,
        public ?int $groupNumber = null,
        public ?string $topic = null,
        public int $declaredDurationMinutes = 0,
        public ?string $objective = null,
        public array $learningAreas = [],
        public array $audienceObjectives = [],
        public array $mandatoryContent = [],
        public array $errorsToAvoid = [],
        public ?string $expectedResult = null,
        /**
         * The author's free notes for this point: everything under the point
         * that is not a recognised brief field (FR-4b). The author's own words
         * outrank the brief and research as a writing source (DEC-6).
         */
        public ?string $notes = null,
    ) {}

    public function hasNotes(): bool
    {
        return $this->notes !== null && trim($this->notes) !== '';
    }

    /**
     * Whether the author should be nudged to enrich this point (FR-5): the
     * brief is thin AND the notes do not carry substance of their own.
     */
    public function needsReview(int $substantialNotesChars = 200): bool
    {
        return $this->isThin() && mb_strlen(trim((string) $this->notes)) < $substantialNotesChars;
    }

    /**
     * A copy re-assigned to another group, used when the parser synthesises the
     * implicit group for an index without one.
     */
    public function inGroup(int $groupNumber): self
    {
        return clone ($this, ['groupNumber' => $groupNumber]);
    }

    /**
     * The index gave this point little beyond its title.
     *
     * Reported so the author can enrich it by hand if they want to, and so the
     * research stage knows it is carrying the whole weight for this point. It is
     * **not** a failure and never blocks generation (FR-5, FR-4a) — for a
     * generic index, thin is the normal case rather than the exception.
     */
    public function isThin(): bool
    {
        return $this->objective === null
            && $this->mandatoryContent === []
            && $this->expectedResult === null
            && $this->learningAreas === [];
    }

    /**
     * True when the index supplied a full brief, the way the reference course
     * does for all of its points. Research then supplements rather than
     * substitutes.
     */
    public function hasFullBrief(): bool
    {
        return $this->objective !== null
            && $this->expectedResult !== null
            && $this->mandatoryContent !== [];
    }

    /**
     * Sections the outline stage is expected to produce, used by the call
     * estimator before a run is confirmed (spec US-12).
     *
     * The divisor is configuration, not a domain rule: it is an empirical ratio
     * measured from the reference scripts, where a 9-minute point maps to 6
     * numbered sections. A point with no declared duration falls back to the
     * caller's default rather than estimating zero sections.
     */
    public function expectedSectionCount(float $minutesPerSection, int $fallbackSections = 5): int
    {
        if ($minutesPerSection <= 0.0) {
            throw new \InvalidArgumentException('Minutes per section must be positive.');
        }

        if ($this->declaredDurationMinutes <= 0) {
            return $fallbackSections;
        }

        return max(1, (int) round($this->declaredDurationMinutes / $minutesPerSection));
    }
}
