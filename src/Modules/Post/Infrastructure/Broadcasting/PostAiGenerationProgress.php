<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Broadcasting;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Real-time progress tick for the AI-assist panel (Post module). Broadcast
 * synchronously (no queue) since the generation call itself is already a
 * blocking HTTP request — the socket push just narrates what's happening
 * while the caller waits for the JSON response. Sent on the causer's own
 * private channel (`routes/channels.php`), already used for per-user pushes.
 */
final readonly class PostAiGenerationProgress implements ShouldBroadcastNow
{
    public function __construct(
        private int $userId,
        private string $flow,
        private string $stage,
        private string $message,
        private int $progress,
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
        return 'post.ai.progress';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'flow' => $this->flow,
            'stage' => $this->stage,
            'message' => $this->message,
            'progress' => $this->progress,
        ];
    }
}
