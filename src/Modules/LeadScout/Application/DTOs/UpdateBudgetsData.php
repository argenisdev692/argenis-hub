<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Editable monthly limits (spec US-8, plan §5 `BudgetData[]`).
 */
#[MapInputName(SnakeCaseMapper::class)]
final class UpdateBudgetsData extends Data
{
    /**
     * @param  list<array{category: string, limit_eur: float}>  $budgets
     */
    public function __construct(
        public array $budgets,
    ) {}

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'budgets' => ['required', 'array', 'min:1', 'max:10'],
            'budgets.*.category' => ['required', 'string', 'in:'.implode(',', BudgetCategory::values())],
            'budgets.*.limit_eur' => ['required', 'numeric', 'min:0', 'max:10000'],
        ];
    }
}
