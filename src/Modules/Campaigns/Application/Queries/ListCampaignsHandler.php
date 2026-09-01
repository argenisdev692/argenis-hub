<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Campaigns\Application\DTOs\CampaignFilterData;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;

final readonly class ListCampaignsHandler
{
    public function __construct(private CampaignRepositoryPort $campaigns) {}

    public function handle(CampaignFilterData $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->campaigns->paginate($filters, $perPage);
    }
}
