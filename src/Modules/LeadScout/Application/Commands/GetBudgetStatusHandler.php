<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Application\DTOs\BudgetStatusData;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Infrastructure\Budgets\BudgetLedger;

/**
 * Budget month read (spec US-8, T040).
 */
final readonly class GetBudgetStatusHandler
{
    public function __construct(private BudgetLedger $ledger) {}

    public function handle(): BudgetStatusData
    {
        return BudgetStatusData::current(BudgetCategory::cases(), $this->ledger);
    }
}
