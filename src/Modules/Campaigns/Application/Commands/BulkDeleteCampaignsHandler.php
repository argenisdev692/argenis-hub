<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Soft-deletes a set of campaigns by UUID. Authorization
 * (permission:BULK_DELETE_CAMPAIGNS) is enforced at the route.
 */
final readonly class BulkDeleteCampaignsHandler
{
    public function __construct(private CampaignRepositoryPort $campaigns) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn (): int => $this->campaigns->bulkSoftDeleteByUuid($data->uuids));
    }
}
