<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Persistence\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Campaigns\Application\DTOs\CampaignFilterData;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Shared\Infrastructure\Persistence\Concerns\BulkSoftDeletesByUuid;

/**
 * Eloquent adapter for {@see CampaignRepositoryPort}. Bulk soft-delete/
 * restore are inherited from the Shared {@see BulkSoftDeletesByUuid} trait
 * (DRY, same as EloquentSocialMediaContentRepository).
 */
final class EloquentCampaignRepository implements CampaignRepositoryPort
{
    use BulkSoftDeletesByUuid;

    /**
     * @return class-string<CampaignEloquentModel>
     */
    protected function model(): string
    {
        return CampaignEloquentModel::class;
    }

    public function paginate(CampaignFilterData $filters, int $perPage): LengthAwarePaginator
    {
        return CampaignEloquentModel::query()
            ->when($filters->status === 'suspended', fn ($q) => $q->onlyTrashed())
            ->applyFilters($filters)
            ->with('creator:id,first_name,last_name')
            ->select([
                'id', 'uuid', 'topic', 'status', 'business_goal', 'funnel_stage', 'platform',
                'ad_format', 'language', 'provider', 'overall_score_avg', 'success_probability_label',
                'all_scores_pass', 'quality_warning', 'scheduled_at', 'published_at', 'created_by',
                'created_at', 'deleted_at',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): ?CampaignEloquentModel
    {
        return CampaignEloquentModel::withTrashed()
            ->with('creator:id,first_name,last_name')
            ->where('uuid', $uuid)
            ->first();
    }

    public function dueForScheduledPublishing(): Collection
    {
        return CampaignEloquentModel::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();
    }

    public function create(array $attributes): CampaignEloquentModel
    {
        return CampaignEloquentModel::query()->create($attributes);
    }

    public function update(CampaignEloquentModel $campaign, array $attributes): CampaignEloquentModel
    {
        $campaign->update($attributes);

        return $campaign->refresh();
    }

    public function softDelete(string $uuid): bool
    {
        return (bool) CampaignEloquentModel::query()->where('uuid', $uuid)->delete();
    }

    public function restore(string $uuid): bool
    {
        return (bool) CampaignEloquentModel::onlyTrashed()->where('uuid', $uuid)->restore();
    }
}
