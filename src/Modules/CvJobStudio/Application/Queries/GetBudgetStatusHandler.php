<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Carbon\CarbonImmutable;
use Modules\CvJobStudio\Application\DTOs\StudioBudgetData;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioBudgetEloquentModel;

/** Spend vs limit per category for the current period (FR-33, NFR-4). */
final readonly class GetBudgetStatusHandler
{
    /** @return list<StudioBudgetData> */
    #[\NoDiscard]
    public function handle(int $userId): array
    {
        return StudioBudgetEloquentModel::query()
            ->where('user_id', $userId)
            ->where('period', CarbonImmutable::now()->format('Y-m'))
            ->get(['category', 'limit_micros', 'spent_micros'])
            ->map(static fn (StudioBudgetEloquentModel $budget): StudioBudgetData => new StudioBudgetData(
                category: $budget->category,
                limitMicros: (int) $budget->limit_micros,
                spentMicros: (int) $budget->spent_micros,
            ))
            ->all();
    }
}
