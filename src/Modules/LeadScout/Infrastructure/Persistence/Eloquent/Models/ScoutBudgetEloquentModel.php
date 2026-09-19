<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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
    use LogsActivity;

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['period', 'category', 'limit_micros'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('lead-scout.budget');
    }
}
