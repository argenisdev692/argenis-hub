<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioBudgetEloquentModel;

/** Spend vs limit per category for the current period (FR-33, NFR-4). */
final readonly class GetBudgetStatusHandler
{
    /** @return list<array{category: string, limit_micros: int, spent_micros: int}> */
    #[\NoDiscard]
    public function handle(int $userId): array
    {
        return StudioBudgetEloquentModel::query()
            ->where('user_id', $userId)
            ->where('period', CarbonImmutable::now()->format('Y-m'))
            ->get(['category', 'limit_micros', 'spent_micros'])
            ->map(static fn ($budget): array => [
                'category' => $budget->category,
                'limit_micros' => (int) $budget->limit_micros,
                'spent_micros' => (int) $budget->spent_micros,
            ])
            ->all();
    }
}
