<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * What the render does to the kept ranges besides joining them: waits played
 * faster and breaths turned down. Both are expressed on the source timeline.
 *
 * `seamClearances` rides along because it is the same kind of thing — a
 * measurement about where speech is, handed to the filter graph so a join can
 * fade into the air that is actually there. It is aligned with the plan's keep
 * ranges by index; an empty list simply means "nothing is known", and the graph
 * then falls back to its configured fade rather than to silence.
 */
final readonly class RenderEffects
{
    /**
     * @param  list<CompressedRange>  $compressedRanges
     * @param  list<TimeRange>  $attenuatedRanges
     * @param  list<array{0: int, 1: int}>  $seamClearances  head/tail silence per keep range, aligned by index
     */
    public function __construct(
        public array $compressedRanges = [],
        public array $attenuatedRanges = [],
        public float $attenuationDb = -15.0,
        public array $seamClearances = [],
    ) {}

    public static function fromPlan(CutPlan $plan, float $attenuationDb): self
    {
        return new self($plan->compressedRanges, $plan->attenuatedRanges, $attenuationDb, $plan->seamClearances);
    }

    public function isEmpty(): bool
    {
        return $this->compressedRanges === [] && $this->attenuatedRanges === [];
    }

    public function savedMs(): int
    {
        return array_sum(array_map(
            static fn (CompressedRange $range): int => $range->savedMs(),
            $this->compressedRanges,
        ));
    }
}
