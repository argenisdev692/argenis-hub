<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;

/**
 * Restores a set of soft-deleted campaigns by UUID. Authorization
 * (permission:BULK_RESTORE_CAMPAIGNS) is enforced at the route.
 */
final readonly class BulkRestoreCampaignsHandler
{
    public function __construct(private CampaignRepositoryPort $campaigns) {}

    public function handle(BulkUuidsData $data): int
    {
        return DB::transaction(fn (): int => $this->campaigns->bulkRestoreByUuid($data->uuids));
    }
}
