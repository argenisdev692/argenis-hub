<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;

/**
 * Soft-deletes a single campaign by UUID. Generated assets (cover images)
 * are intentionally kept on R2 so a restore is lossless. Authorization
 * (permission:DELETE_CAMPAIGNS) is enforced at the route.
 */
final readonly class DeleteCampaignHandler
{
    public function __construct(private CampaignRepositoryPort $campaigns) {}

    public function handle(string $uuid): bool
    {
        return DB::transaction(fn (): bool => $this->campaigns->softDelete($uuid));
    }
}
