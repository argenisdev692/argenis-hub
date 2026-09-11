<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\Enums\DecisionRejectionReason;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\ValidatedDecision;

/**
 * Validates every decision against the real media duration — the same gate for
 * manual ranges today and AI output in V3 (EX-3). It never throws: each decision
 * is applied or rejected with a reason, and the pipeline decides whether a
 * rejection is fatal (user ranges, P1) or just reported (AI output).
 */
final readonly class CutDecisionValidator
{
    /**
     * @param  list<CutDecision>  $decisions
     * @return list<ValidatedDecision>
     */
    #[\NoDiscard]
    public function validate(array $decisions, int $durationMs): array
    {
        return array_map(
            fn (CutDecision $decision): ValidatedDecision => $this->validateOne($decision, $durationMs),
            $decisions,
        );
    }

    private function validateOne(CutDecision $decision, int $durationMs): ValidatedDecision
    {
        $rejection = match (true) {
            $decision->startMs < 0 => DecisionRejectionReason::NegativeStart,
            $decision->endMs <= $decision->startMs => DecisionRejectionReason::StartNotBeforeEnd,
            $decision->endMs > $durationMs => DecisionRejectionReason::EndBeyondDuration,
            $decision->confidence !== null && ($decision->confidence < 0.0 || $decision->confidence > 1.0) => DecisionRejectionReason::ConfidenceOutOfRange,
            default => null,
        };

        return $rejection === null
            ? ValidatedDecision::applied($decision)
            : ValidatedDecision::rejected($decision, $rejection);
    }
}
