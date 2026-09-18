<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\LeadScout\Domain\Enums\BudgetCategory;

/**
 * Monthly spend ledger per category (spec US-8, FR-14). `spent_micros` moves
 * only through atomic increments in `BudgetLedger` — never direct writes.
 *
 * @property int $id
 *
 * @mixin \Eloquent
 *
 * @internal
 */
#[Table('scout_budgets')]
#[Fillable([
    'period',
    'category',
    'limit_micros',
    'spent_micros',
])]
final class ScoutBudgetEloquentModel extends Model
{
    /** @var list<string> */
    protected $hidden = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => BudgetCategory::class,
            'limit_micros' => 'integer',
            'spent_micros' => 'integer',
        ];
    }
}
