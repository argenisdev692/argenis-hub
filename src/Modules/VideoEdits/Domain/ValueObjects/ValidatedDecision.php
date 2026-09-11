<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Domain\Enums\DecisionRejectionReason;

/**
 * A cut decision after validation and planning: applied (optionally linked to
 * the applied cut it was merged into) or rejected with a reason.
 */
final readonly class ValidatedDecision
{
    private function __construct(
        public CutDecision $decision,
        public DecisionOutcome $outcome,
        public ?DecisionRejectionReason $rejectionReason = null,
        public ?int $appliedCutSequence = null,
    ) {}

    public static function applied(CutDecision $decision): self
    {
        return new self($decision, DecisionOutcome::Applied);
    }

    public static function rejected(CutDecision $decision, DecisionRejectionReason $reason): self
    {
        return new self($decision, DecisionOutcome::Rejected, $reason);
    }

    public function isApplied(): bool
    {
        return $this->outcome === DecisionOutcome::Applied;
    }

    public function withAppliedCutSequence(int $sequence): self
    {
        return clone ($this, ['appliedCutSequence' => $sequence]);
    }
}
