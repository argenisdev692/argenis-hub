<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Domain\ValueObjects\DurationPlan;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The duration plan as the report and the PDF show it (US-14).
 *
 * Shaped for the reader rather than for the storage: every number that answers
 * "how did we get here" travels, including the ones that say the target was not
 * reached. A plan that only showed the good news would be the very thing this
 * feature exists to stop.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class DurationPlanData extends Data
{
    /**
     * @param  list<array{start_ms: int, end_ms: int, gap_ms: int, after_sentence_end: bool}>  $longestGaps
     */
    public function __construct(
        public int $totalMs,
        public ?int $targetMs,
        public ?int $requiredRemovalMs,
        public int $plannedRemovalMs,
        public int $projectedMs,
        public ?int $shortfallMs,
        public string $verdict,
        public int $gapCount,
        public int $deadAirMs,
        public int $sentenceGapMs,
        public int $intraPhraseGapMs,
        public int $silenceRecoverableMs,
        public int $aiProposalMs,
        public int $speechCutMs,
        public int $manualCutMs,
        public int $plannedCutMs,
        public array $longestGaps,
    ) {}

    public static function fromValueObject(DurationPlan $plan): self
    {
        return new self(
            totalMs: $plan->totalMs,
            targetMs: $plan->targetMs,
            requiredRemovalMs: $plan->requiredRemovalMs(),
            plannedRemovalMs: $plan->plannedRemovalMs(),
            projectedMs: $plan->projectedMs(),
            shortfallMs: $plan->shortfallMs(),
            verdict: $plan->verdict(),
            gapCount: $plan->gapCount,
            deadAirMs: $plan->deadAirMs,
            sentenceGapMs: $plan->sentenceGapMs,
            intraPhraseGapMs: $plan->intraPhraseGapMs,
            silenceRecoverableMs: $plan->silenceRecoverableMs,
            aiProposalMs: $plan->aiProposalMs,
            speechCutMs: $plan->speechCutMs,
            manualCutMs: $plan->manualCutMs,
            plannedCutMs: $plan->plannedCutMs,
            longestGaps: $plan->longestGaps,
        );
    }
}
