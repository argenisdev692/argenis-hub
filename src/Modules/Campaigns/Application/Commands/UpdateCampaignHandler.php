<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\Commands;

use Illuminate\Support\Facades\DB;
use Modules\Campaigns\Application\DTOs\UpdateCampaignData;
use Modules\Campaigns\Domain\Ports\CampaignRepositoryPort;
use Modules\Campaigns\Infrastructure\Persistence\Eloquent\Models\CampaignEloquentModel;

/**
 * Persists the human review/edit pass over an AI-generated campaign.
 * Authorization (permission:UPDATE_CAMPAIGNS) is enforced at the route.
 */
final readonly class UpdateCampaignHandler
{
    public function __construct(private CampaignRepositoryPort $campaigns) {}

    public function handle(CampaignEloquentModel $campaign, UpdateCampaignData $data): CampaignEloquentModel
    {
        return DB::transaction(fn (): CampaignEloquentModel => $this->campaigns->update($campaign, [
            'headline' => $data->headline,
            'primary_text' => $data->primaryText,
            'description' => $data->description,
            'call_to_action' => $data->callToAction,
            'hashtags' => $data->hashtags,
            'lead_form_questions' => $data->leadFormQuestions,
            'status' => $data->status,
            'scheduled_at' => $data->scheduledAt,
            'published_at' => $data->status === 'published' ? now() : $campaign->published_at,
        ]));
    }
}
