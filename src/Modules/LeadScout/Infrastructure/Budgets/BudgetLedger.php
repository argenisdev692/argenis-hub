<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Budgets;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Exceptions\BudgetExceededException;
use Modules\LeadScout\Domain\Ports\BudgetLedgerPort;
use Modules\LeadScout\Infrastructure\Persistence\Eloquent\Models\ScoutBudgetEloquentModel;

/**
 * Monthly spend ledger (spec US-8, FR-14, T040). Limits come from config
 * (editable via PUT budgets); spend moves only through atomic increments
 * on every paid search/extraction/AI call.
 */
final readonly class BudgetLedger implements BudgetLedgerPort
{
    public static function period(?\DateTimeInterface $at = null): string
    {
        return ($at === null ? now() : CarbonImmutable::parse($at->format('Y-m-d')))->format('Y-m');
    }

    public static function limitMicros(BudgetCategory $category): int
    {
        return (int) ((float) config("lead-scout.budgets.{$category->value}.limit_eur", 0) * 1_000_000);
    }

    public function currentPeriod(): string
    {
        return self::period();
    }

    public function updateLimits(array $limitMicrosByCategory): void
    {
        DB::transaction(static function () use ($limitMicrosByCategory): void {
            foreach ($limitMicrosByCategory as $category => $limitMicros) {
                ScoutBudgetEloquentModel::query()->updateOrCreate(
                    ['period' => self::period(), 'category' => $category],
                    ['limit_micros' => $limitMicros],
                );
            }
        });
    }

    /**
     * @throws BudgetExceededException
     */
    public function ensure(BudgetCategory $category, int $micros): void
    {
        $row = $this->row($category);

        if ($row->spent_micros + $micros > $row->limit_micros) {
            throw new BudgetExceededException($category->value);
        }
    }

    public function spend(BudgetCategory $category, int $micros): void
    {
        if ($micros <= 0) {
            return;
        }

        $this->row($category);

        ScoutBudgetEloquentModel::query()
            ->where('period', self::period())
            ->where('category', $category->value)
            ->increment('spent_micros', $micros);
    }

    /**
     * @return array{limit_micros: int, spent_micros: int, percent: float, exhausted: bool}
     */
    #[\NoDiscard]
    public function status(BudgetCategory $category): array
    {
        $row = $this->row($category);
        $limit = (int) $row->limit_micros;
        $spent = (int) $row->spent_micros;

        return [
            'limit_micros' => $limit,
            'spent_micros' => $spent,
            'percent' => $limit > 0 ? round($spent / $limit * 100, 1) : 0.0,
            'exhausted' => $limit > 0 && $spent >= $limit,
        ];
    }

    private function row(BudgetCategory $category): ScoutBudgetEloquentModel
    {
        return DB::transaction(static function () use ($category): ScoutBudgetEloquentModel {
            $row = ScoutBudgetEloquentModel::query()
                ->where('period', self::period())
                ->where('category', $category->value)
                ->lockForUpdate()
                ->first();

            if ($row !== null) {
                return $row;
            }

            return ScoutBudgetEloquentModel::query()->create([
                'period' => self::period(),
                'category' => $category->value,
                'limit_micros' => self::limitMicros($category),
                'spent_micros' => 0,
            ]);
        });
    }
}
