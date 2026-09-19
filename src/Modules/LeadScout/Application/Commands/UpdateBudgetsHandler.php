<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Modules\LeadScout\Application\DTOs\BudgetStatusData;
use Modules\LeadScout\Application\DTOs\UpdateBudgetsData;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Exceptions\InvalidInputException;
use Modules\LeadScout\Domain\Ports\BudgetLedgerPort;

/**
 * Editable budget limits (spec US-8, T040). Negative values already fail
 * at 422 in the DTO; spent counters are never writable here.
 */
final readonly class UpdateBudgetsHandler
{
    public function __construct(private BudgetLedgerPort $ledger) {}

    public function handle(UpdateBudgetsData $data): BudgetStatusData
    {
        if (count($data->budgets) !== count(array_unique(array_column($data->budgets, 'category')))) {
            throw InvalidInputException::withMessages(['budgets' => 'Duplicate category.']);
        }

        $limits = [];

        foreach ($data->budgets as $row) {
            $limits[BudgetCategory::from($row['category'])->value] = (int) round((float) $row['limit_eur'] * 1_000_000);
        }

        $this->ledger->updateLimits($limits);

        return BudgetStatusData::current(BudgetCategory::cases(), $this->ledger);
    }
}
