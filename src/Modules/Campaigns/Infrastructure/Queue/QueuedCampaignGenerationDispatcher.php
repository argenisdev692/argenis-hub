<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Queue;

use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Domain\Ports\CampaignGenerationDispatcherPort;

/**
 * @see CampaignGenerationDispatcherPort
 */
final readonly class QueuedCampaignGenerationDispatcher implements CampaignGenerationDispatcherPort
{
    public function dispatch(string $campaignUuid, GenerateCampaignData $data, ?int $causerId = null): void
    {
        GenerateCampaignJob::dispatch($campaignUuid, $data, $causerId);
    }
}
