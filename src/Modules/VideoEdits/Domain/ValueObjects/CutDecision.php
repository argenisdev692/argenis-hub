<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Services\CutDecisionValidator;

/**
 * The single cut-decision contract every producer emits (EX-1): silence
 * detection and manual ranges in V1, speech detectors in V2, the AI analyzer in
 * V3. Deliberately NOT self-validating — out-of-range values must survive until
 * {@see CutDecisionValidator} can reject them
 * with a reason (EX-3, EX-8).
 */
final readonly class CutDecision
{
    /**
     * @param  array<string, scalar|null>|null  $evidence
     */
    public function __construct(
        public string $producer,
        public CutReason $reason,
        public DecisionOrigin $origin,
        public int $startMs,
        public int $endMs,
        public ?float $confidence = null,
        public ?array $evidence = null,
    ) {}
}
