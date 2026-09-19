<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Ports;

use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;

/**
 * Monthly spend ledger per category (spec US-8, FR-14).
 */
interface BudgetLedgerPort
{
    /** Current budget period, `Y-m`. */
    public function currentPeriod(): string;

    /**
     * @throws BudgetExceededException
     */
    public function ensure(BudgetCategory $category, int $micros): void;

    public function spend(BudgetCategory $category, int $micros): void;

    /**
     * @return array{limit_micros: int, spent_micros: int, percent: float, exhausted: bool}
     */
    public function status(BudgetCategory $category): array;

    /**
     * Replaces the current period's limits in one transaction.
     *
     * @param  array<string, int>  $limitMicrosByCategory  category value → limit in micros
     */
    public function updateLimits(array $limitMicrosByCategory): void;
}
