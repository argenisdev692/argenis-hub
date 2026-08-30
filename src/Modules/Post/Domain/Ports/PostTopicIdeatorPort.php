<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\DTOs\PostTopicIdeaData;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;

/**
 * Suggests viral, on-brand blog topics grounded in the company profile and
 * current web trends (Tavily). Read-only — never persists anything.
 */
interface PostTopicIdeatorPort
{
    /**
     * @return list<PostTopicIdeaData>
     */
    public function suggestTopics(SuggestPostTopicsData $data, ?object $causer = null): array;
}
