<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * The only input the render step consumes (EX-3): which intervals to keep, plus
 * the applied cuts and every decision's final outcome for persistence (EX-8).
 */
final readonly class CutPlan
{
    /**
     * @param  list<AppliedCut>  $appliedCuts
     * @param  list<TimeRange>  $keepRanges
     * @param  list<ValidatedDecision>  $decisions
     */
    public function __construct(
        public array $appliedCuts,
        public array $keepRanges,
        public array $decisions,
        public int $originalDurationMs,
        public int $finalDurationMs,
    ) {}

    public function removedDurationMs(): int
    {
        return $this->originalDurationMs - $this->finalDurationMs;
    }

    public function hasCuts(): bool
    {
        return $this->appliedCuts !== [];
    }

    public function rejectedDecisionCount(): int
    {
        return count(array_filter(
            $this->decisions,
            static fn (ValidatedDecision $decision): bool => ! $decision->isApplied(),
        ));
    }
}
