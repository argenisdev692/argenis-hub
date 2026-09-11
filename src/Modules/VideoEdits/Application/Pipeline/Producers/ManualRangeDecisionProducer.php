<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline\Producers;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;

/**
 * Turns the user's ranges into decisions (US-3). `range_index` in the evidence
 * lets a rejected range be reported back per index (P1).
 */
final readonly class ManualRangeDecisionProducer implements CutDecisionProducer
{
    public const string NAME = 'manual';

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(DecisionContext $context): bool
    {
        return $context->mode->acceptsCutDecisions()
            && ($context->parameters['manual_ranges'] ?? []) !== [];
    }

    public function produce(DecisionContext $context): array
    {
        $decisions = [];

        foreach (array_values((array) $context->parameters['manual_ranges']) as $index => $range) {
            $decisions[] = new CutDecision(
                producer: self::NAME,
                reason: CutReason::Manual,
                origin: DecisionOrigin::User,
                startMs: (int) $range['start_ms'],
                endMs: (int) $range['end_ms'],
                evidence: ['range_index' => $index, 'note' => $range['note'] ?? null],
            );
        }

        return $decisions;
    }
}
