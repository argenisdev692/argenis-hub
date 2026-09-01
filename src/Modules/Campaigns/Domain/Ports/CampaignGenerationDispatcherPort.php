<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Ports;

use Modules\Campaigns\Application\DTOs\GenerateCampaignData;

/**
 * Kicks off the async quality-loop for one campaign row. Kept as its own
 * tiny port (rather than folding into {@see CampaignGeneratorPort}) so
 * Application depends only on this abstraction and never on the concrete
 * queued Job class (DIP) — the Infrastructure adapter is the only place that
 * knows a queue is involved at all.
 */
interface CampaignGenerationDispatcherPort
{
    public function dispatch(string $campaignUuid, GenerateCampaignData $data, ?int $causerId = null): void;
}
