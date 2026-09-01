<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Ports;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Campaigns\Application\DTOs\CampaignFilterData;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;

interface CampaignRepositoryPort
{
    /**
     * @return LengthAwarePaginator<int, CampaignEloquentModel>
     */
    public function paginate(CampaignFilterData $filters, int $perPage): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?CampaignEloquentModel;

    /**
     * Campaigns whose `scheduled_at` has been reached (cron auto-publish).
     *
     * @return Collection<int, CampaignEloquentModel>
     */
    public function dueForScheduledPublishing(): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CampaignEloquentModel;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CampaignEloquentModel $campaign, array $attributes): CampaignEloquentModel;

    public function softDelete(string $uuid): bool;

    public function restore(string $uuid): bool;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkSoftDeleteByUuid(array $uuids): int;

    /**
     * @param  array<int, string>  $uuids
     */
    public function bulkRestoreByUuid(array $uuids): int;
}
