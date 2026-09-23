<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Modules\VideoEdits\Domain\Enums\DecisionRejectionReason;

/**
 * The only input the render step consumes (EX-3): which intervals to keep, plus
 * the applied cuts and every decision's final outcome for persistence (EX-8).
 *
 * `compressedRanges` and `attenuatedRanges` lie inside the keep ranges: kept,
 * but played faster (tool waits) or quieter (breaths). `finalDurationMs`
 * already accounts for the speed-ups.
 */
final readonly class CutPlan
{
    /**
     * @param  list<AppliedCut>  $appliedCuts
     * @param  list<TimeRange>  $keepRanges
     * @param  list<ValidatedDecision>  $decisions
     * @param  list<CompressedRange>  $compressedRanges
     * @param  list<TimeRange>  $attenuatedRanges
     */
    public function __construct(
        public array $appliedCuts,
        public array $keepRanges,
        public array $decisions,
        public int $originalDurationMs,
        public int $finalDurationMs,
        public array $compressedRanges = [],
        public array $attenuatedRanges = [],
    ) {}

    public function removedDurationMs(): int
    {
        return $this->originalDurationMs - $this->finalDurationMs;
    }

    public function hasCuts(): bool
    {
        return $this->appliedCuts !== [];
    }

    /**
     * Decisions that were dropped. A silence kept because the screen was busy
     * is not counted: it was handled (sped up or kept), not refused.
     */
    public function rejectedDecisionCount(): int
    {
        return count(array_filter(
            $this->decisions,
            static fn (ValidatedDecision $decision): bool => ! $decision->isApplied()
                && $decision->rejectionReason !== DecisionRejectionReason::ScreenActivity,
        ));
    }
}
