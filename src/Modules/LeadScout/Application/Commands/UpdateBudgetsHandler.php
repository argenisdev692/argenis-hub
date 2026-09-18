<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\Commands;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\LeadScout\Application\DTOs\BudgetStatusData;
use Modules\LeadScout\Application\DTOs\UpdateBudgetsData;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Infrastructure\Budgets\BudgetLedger;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutBudgetEloquentModel;

/**
 * Editable budget limits (spec US-8, T040). Negative values already fail
 * at 422 in the DTO; spent counters are never writable here.
 */
final readonly class UpdateBudgetsHandler
{
    public function __construct(private BudgetLedger $ledger) {}

    public function handle(UpdateBudgetsData $data): BudgetStatusData
    {
        if (count($data->budgets) !== count(array_unique(array_column($data->budgets, 'category')))) {
            throw ValidationException::withMessages(['budgets' => 'Duplicate category.']);
        }

        DB::transaction(function () use ($data): void {
            foreach ($data->budgets as $row) {
                $category = BudgetCategory::from($row['category']);
                $micros = (int) round((float) $row['limit_eur'] * 1_000_000);

                $existing = ScoutBudgetEloquentModel::query()
                    ->where('period', BudgetLedger::period())
                    ->where('category', $category->value)
                    ->first();

                if ($existing === null) {
                    ScoutBudgetEloquentModel::query()->create([
                        'period' => BudgetLedger::period(),
                        'category' => $category->value,
                        'limit_micros' => $micros,
                    ]);
                } else {
                    $existing->update(['limit_micros' => $micros]);
                }
            }
        });

        return BudgetStatusData::current(BudgetCategory::cases(), $this->ledger);
    }
}
