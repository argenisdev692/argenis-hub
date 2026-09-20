<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Persistence\Repositories;

use Modules\CvJobStudio\Application\DTOs\StudioBudgetData;
use Modules\CvJobStudio\Application\DTOs\StudioSourceData;
use Modules\CvJobStudio\Domain\Ports\StudioCatalogReadPort;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioBudgetEloquentModel;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSourceEloquentModel;

final readonly class EloquentStudioCatalogReader implements StudioCatalogReadPort
{
    public function budgetsForPeriod(int $userId, string $period): array
    {
        return StudioBudgetEloquentModel::query()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->get(['category', 'limit_micros', 'spent_micros'])
            ->map(static fn (StudioBudgetEloquentModel $budget): StudioBudgetData => new StudioBudgetData(
                category: $budget->category,
                limitMicros: (int) $budget->limit_micros,
                spentMicros: (int) $budget->spent_micros,
            ))
            ->values()
            ->all();
    }

    /** Bounded by the seeded registry (one row per adapter), so it is returned whole. */
    public function sources(int $userId): array
    {
        return StudioSourceEloquentModel::query()
            ->ownedBy($userId)
            ->select(['uuid', 'name', 'kind', 'tier', 'layer', 'status', 'health_checked_at', 'attribution_required', 'access_mode', 'resolution_tier'])
            ->orderBy('resolution_priority')
            ->get()
            ->map(static fn (StudioSourceEloquentModel $source): StudioSourceData => new StudioSourceData(
                uuid: $source->uuid,
                name: $source->name,
                kind: $source->kind,
                tier: (int) $source->tier,
                layer: $source->layer,
                status: $source->status,
                healthCheckedAt: $source->health_checked_at?->toIso8601String(),
                attributionRequired: (bool) $source->attribution_required,
                accessMode: $source->access_mode,
                resolutionTier: (int) $source->resolution_tier,
            ))
            ->values()
            ->all();
    }
}
