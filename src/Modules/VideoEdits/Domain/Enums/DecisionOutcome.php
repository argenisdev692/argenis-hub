<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Enums;

/**
 * Result of validating one cut decision. Rejected decisions are persisted too,
 * so the V3 report can explain what was NOT removed and why (EX-8).
 */
enum DecisionOutcome: string
{
    case Applied = 'applied';
    case Rejected = 'rejected';
}
