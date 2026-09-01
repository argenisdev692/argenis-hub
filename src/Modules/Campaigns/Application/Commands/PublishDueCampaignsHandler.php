<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Shared\Domain\Ports\AuditPort;

/**
 * Auto-publishes campaigns whose `scheduled_at` has been reached. Invoked by
 * the `campaigns:publish-scheduled` Artisan command, which routes/console.php
 * runs every minute. Distinct from {@see PublishCampaignHandler}, which is
 * the manual, human-triggered publish action.
 */
final readonly class PublishDueCampaignsHandler
{
    public function __construct(
        private CampaignRepositoryPort $campaigns,
        private AuditPort $audit,
    ) {}

    public function handle(): int
    {
        $due = $this->campaigns->dueForScheduledPublishing();

        foreach ($due as $campaign) {
            $scheduledAt = $campaign->scheduled_at?->toIso8601String();

            $published = DB::transaction(fn () => $this->campaigns->update($campaign, [
                'status' => 'published',
                'published_at' => now(),
            ]));

            $this->audit->log(
                event: 'campaigns.auto_published',
                subject: $published,
                properties: ['scheduled_at' => $scheduledAt],
                causer: null,
                logName: 'campaigns',
            );
        }

        return $due->count();
    }
}
