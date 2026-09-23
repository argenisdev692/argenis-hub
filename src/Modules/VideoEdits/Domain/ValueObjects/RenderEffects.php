<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * What the render does to the kept ranges besides joining them: waits played
 * faster and breaths turned down. Both are expressed on the source timeline.
 */
final readonly class RenderEffects
{
    /**
     * @param  list<CompressedRange>  $compressedRanges
     * @param  list<TimeRange>  $attenuatedRanges
     */
    public function __construct(
        public array $compressedRanges = [],
        public array $attenuatedRanges = [],
        public float $attenuationDb = -15.0,
    ) {}

    public static function fromPlan(CutPlan $plan, float $attenuationDb): self
    {
        return new self($plan->compressedRanges, $plan->attenuatedRanges, $attenuationDb);
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
