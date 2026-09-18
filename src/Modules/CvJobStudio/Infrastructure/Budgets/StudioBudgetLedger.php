<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Budgets;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\CvJobStudio\Domain\Exceptions\BudgetExceededException;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioBudgetEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioProviderCallEloquentModel;

/**
 * Period budget ledger modelled on LeadScout's `BudgetLedger` (T-097, FR-33,
 * SC-8). `ensure()` runs BEFORE the provider call under `lockForUpdate`;
 * a refused call never spends. Concurrency-safe by row lock.
 */
final readonly class StudioBudgetLedger implements SpendGuardPort
{
    public function ensure(string $category, int $userId, ?int $runId = null): void
    {
        DB::transaction(function () use ($category, $userId): void {
            $budget = StudioBudgetEloquentModel::query()
                ->where('user_id', $userId)
                ->where('period', CarbonImmutable::now()->format('Y-m'))
                ->where('category', $category)
                ->lockForUpdate()
                ->first();

            if ($budget === null || $budget->spent_micros >= $budget->limit_micros) {
                throw new BudgetExceededException("Period budget exceeded for {$category}.");
            }
        });
    }

    public function record(string $category, int $userId, string $provider, string $operation, int $costMicros, bool $succeeded, ?int $runId = null): void
    {
        DB::transaction(function () use ($category, $userId, $provider, $operation, $costMicros, $succeeded, $runId): void {
            $budget = StudioBudgetEloquentModel::query()
                ->where('user_id', $userId)
                ->where('period', CarbonImmutable::now()->format('Y-m'))
                ->where('category', $category)
                ->lockForUpdate()
                ->first();

            if ($budget !== null) {
                $budget->update(['spent_micros' => $budget->spent_micros + $costMicros]);
            }

            StudioProviderCallEloquentModel::query()->create([
                'user_id' => $userId,
                'run_id' => $runId,
                'category' => $category,
                'provider' => $provider,
                'operation' => $operation,
                'cost_micros' => $costMicros,
                'succeeded' => $succeeded,
                'called_at' => now(),
            ]);
        });
    }
}
