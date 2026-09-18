<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * The decision rule is locked and immutable (spec FR-19). Rendered as 409.
 */
final class DecisionRuleLockedException extends RuntimeException
{
    public const string CODE = 'DECISION_RULE_LOCKED';

    public function __construct()
    {
        parent::__construct('This decision rule is already locked. Create a new version for a new period.');
    }
}
