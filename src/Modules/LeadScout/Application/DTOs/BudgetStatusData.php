<?php

declare(strict_types=1);

namespace Modules\LeadScout\Application\DTOs;

use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Ports\BudgetLedgerPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Budget month status per category (plan §5 `BudgetStatusData`).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class BudgetStatusData extends Data
{
    /**
     * @param  list<array{category: string, limit_micros: int, spent_micros: int, percent: float, exhausted: bool}>  $categories
     */
    public function __construct(
        public readonly string $period,
        public readonly array $categories,
    ) {}

    /**
     * @param  list<BudgetCategory>  $categories
     */
    public static function current(array $categories, BudgetLedgerPort $ledger): self
    {
        $rows = [];

        foreach ($categories as $category) {
            $rows[] = ['category' => $category->value, ...$ledger->status($category)];
        }

        return new self($ledger->currentPeriod(), $rows);
    }
}
