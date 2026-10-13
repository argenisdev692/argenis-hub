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
 *
 * `seamClearances` is what the render needs to know about where the SEAMS are.
 * It is aligned with `keepRanges` by index and carries, per kept fragment, the
 * silence measured at its head and at its tail. An `afade` is a gain ramp, so
 * one applied where there is no air ramps a real word down instead of a
 * waveform to zero — which is how a single word ends up quieter than the rest.
 * With this the renderer can shorten the fade to the silence that actually
 * exists instead of assuming there always is some.
 */
final readonly class CutPlan
{
    /**
     * @param  list<AppliedCut>  $appliedCuts
     * @param  list<TimeRange>  $keepRanges
     * @param  list<ValidatedDecision>  $decisions
     * @param  list<CompressedRange>  $compressedRanges
     * @param  list<TimeRange>  $attenuatedRanges
     * @param  list<array{0: int, 1: int}>  $seamClearances  head/tail silence per keep range, aligned by index
     */
    public function __construct(
        public array $appliedCuts,
        public array $keepRanges,
        public array $decisions,
        public int $originalDurationMs,
        public int $finalDurationMs,
        public array $compressedRanges = [],
        public array $attenuatedRanges = [],
        public array $seamClearances = [],
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
