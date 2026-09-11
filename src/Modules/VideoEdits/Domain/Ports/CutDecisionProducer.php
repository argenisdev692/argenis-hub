<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;

/**
 * The extension seam of the editor (EX-2). V1 ships silence detection and
 * manual ranges; V2 registers speech detectors and V3 the AI analyzer as further
 * implementations. Producers only PROPOSE decisions — validation, planning,
 * rendering, persistence and deletion never change when one is added.
 */
interface CutDecisionProducer
{
    /**
     * Stable identifier persisted on every decision (e.g. `silence_detector`).
     */
    public function name(): string;

    public function supports(DecisionContext $context): bool;

    /**
     * @return list<CutDecision>
     */
    public function produce(DecisionContext $context): array;
}
