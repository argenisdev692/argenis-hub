<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Domain\Ports\CampaignGenerationDispatcherPort;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Step 2 entry point: persists a `generating` placeholder row and hands off
 * to the async quality-loop (see {@see CampaignGenerationDispatcherPort}).
 * Returns immediately — the frontend polls/subscribes to the row's status
 * instead of blocking on up to 5 Tavily+AI+image iterations in one HTTP
 * request.
 */
final readonly class GenerateCampaignHandler
{
    public function __construct(
        private CampaignRepositoryPort $campaigns,
        private CampaignGenerationDispatcherPort $dispatcher,
        private AuditPort $audit,
    ) {}

    #[\NoDiscard]
    public function handle(GenerateCampaignData $data, object $causer): CampaignEloquentModel
    {
        $campaign = DB::transaction(fn (): CampaignEloquentModel => $this->campaigns->create([
            'niche' => $data->niche,
            'topic' => $data->topic,
            'angle' => $data->angle,
            'hook' => $data->hook,
            'key_trend' => $data->keyTrend,
            'audience' => $data->audience,
            'business_goal' => $data->businessGoal,
            'brand_voice' => $data->brandVoice,
            'funnel_stage' => $data->funnelStage,
            'platform' => $data->platform,
            'ad_format' => $data->adFormat,
            'language' => $data->language,
            'provider' => $data->provider,
            'status' => 'generating',
            'created_by' => $causer->id,
        ]));

        $this->dispatcher->dispatch($campaign->uuid, $data, (int) $causer->getAuthIdentifier());

        $this->audit->log(
            event: 'campaigns.ai.generation_started',
            subject: $campaign,
            properties: [
                'provider' => $data->provider,
                'topic' => $data->topic,
                'funnel_stage' => $data->funnelStage,
                'platform' => $data->platform,
                'ad_format' => $data->adFormat,
                'generate_images' => $data->generateImages,
            ],
            causer: $causer,
            logName: 'campaigns',
        );

        return $campaign;
    }
}
