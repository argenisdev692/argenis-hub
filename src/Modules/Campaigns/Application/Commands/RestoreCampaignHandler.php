<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;

/**
 * Restores a soft-deleted campaign by UUID. Authorization
 * (permission:RESTORE_CAMPAIGNS) is enforced at the route.
 */
final readonly class RestoreCampaignHandler
{
    public function __construct(private CampaignRepositoryPort $campaigns) {}

    public function handle(string $uuid): bool
    {
        return DB::transaction(fn (): bool => $this->campaigns->restore($uuid));
    }
}
