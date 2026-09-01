<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Modules\SocialMedia\Infrastructure\Broadcasting\SocialMediaAiGenerationProgress;

/**
 * Real-time progress tick for the quality-loop Job. Broadcast immediately
 * (no extra queue hop) since the Job itself already runs on the queue worker
 * — mirrors {@see SocialMediaAiGenerationProgress}.
 * Sent on the causer's own private channel (`routes/channels.php`).
 */
final readonly class CampaignAiGenerationProgress implements ShouldBroadcastNow
{
    public function __construct(
        private int $userId,
        private string $campaignUuid,
        private string $stage,
        private string $message,
        private int $progress,
        private int $iteration = 1,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'campaigns.ai.progress';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'campaign_uuid' => $this->campaignUuid,
            'stage' => $this->stage,
            'message' => $this->message,
            'progress' => $this->progress,
            'iteration' => $this->iteration,
        ];
    }
}
