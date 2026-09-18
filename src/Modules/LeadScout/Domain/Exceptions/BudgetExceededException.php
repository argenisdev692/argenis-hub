<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Exceptions;

use RuntimeException;

/**
 * Monthly category budget exhausted (spec US-8, FR-14). The pipeline
 * degrades to free sources instead of spending. Rendered as 402.
 */
final class BudgetExceededException extends RuntimeException
{
    public const string CODE = 'BUDGET_EXHAUSTED';

    public function __construct(string $category)
    {
        parent::__construct("Monthly {$category} budget exhausted. Free sources continue.");
    }
}
