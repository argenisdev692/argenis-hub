<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Marks a reviewed campaign as `published` (the row records the decision —
 * actually launching it in Meta Ads Manager is a manual, external step in
 * v1). The user may publish from `ready` or `needs_review` alike:
 * `all_scores_pass` is informational, not a hard gate, since a human is
 * reviewing before this call. Authorization (permission:PUBLISH_CAMPAIGNS)
 * is enforced at the route.
 */
final readonly class PublishCampaignHandler
{
    public function __construct(
        private CampaignRepositoryPort $campaigns,
        private AuditPort $audit,
    ) {}

    public function handle(CampaignEloquentModel $campaign, ?object $causer = null): CampaignEloquentModel
    {
        $published = DB::transaction(fn (): CampaignEloquentModel => $this->campaigns->update($campaign, [
            'status' => 'published',
            'published_at' => now(),
        ]));

        $this->audit->log(
            event: 'campaigns.published',
            subject: $published,
            properties: ['all_scores_pass' => $published->all_scores_pass],
            causer: $causer,
            logName: 'campaigns',
        );

        return $published;
    }
}
