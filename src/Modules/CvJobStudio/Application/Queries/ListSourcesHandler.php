<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Application\Queries;

use Modules\CvJobStudio\Application\DTOs\StudioSourceData;
use Modules\CvJobStudio\Infrastructure\Persistence\Eloquent\Models\StudioSourceEloquentModel;

/**
 * The owner's discovery-source catalogue in resolution order. Bounded by the
 * seeded registry (one row per adapter), so it is returned whole.
 */
final readonly class ListSourcesHandler
{
    /** @return list<StudioSourceData> */
    #[\NoDiscard]
    public function handle(int $userId): array
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
