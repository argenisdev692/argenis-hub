<?php

declare(strict_types=1);

namespace Modules\Post\Application\Commands;

use Modules\Post\Application\DTOs\PostTopicIdeaData;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;
use Modules\Post\Domain\Ports\PostTopicIdeatorPort;
use Shared\Domain\Ports\AuditPort;

/**
 * Imperative AI action (no DB write) — same shape as Backup's
 * `RunBackupHandler`: it lives under Commands because it is a costly,
 * non-idempotent external call, even though it does not mutate a Post
 * aggregate. Authorization (permission:CREATE_POSTS) is enforced at the route.
 * Meta-audited (like `RunBackupHandler`) since every call is a real, billed
 * provider request — the trail records who spent the AI credits.
 */
final readonly class SuggestPostTopicsHandler
{
    public function __construct(
        private PostTopicIdeatorPort $ideator,
        private AuditPort $audit,
    ) {}

    /**
     * @return list<PostTopicIdeaData>
     */
    public function handle(SuggestPostTopicsData $data, ?object $causer = null): array
    {
        $ideas = $this->ideator->suggestTopics($data, $causer);

        $this->audit->log(
            event: 'post.ai.topics_suggested',
            properties: ['provider' => $data->provider, 'topic' => $data->topic, 'idea_count' => count($ideas)],
            causer: $causer,
            logName: 'post',
        );

        return $ideas;
    }
}
