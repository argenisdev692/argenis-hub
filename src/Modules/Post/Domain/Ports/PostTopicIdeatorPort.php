<?php

declare(strict_types=1);

namespace Modules\Post\Domain\Ports;

use Modules\Post\Application\DTOs\PostTopicIdeaData;
use Modules\Post\Application\DTOs\SuggestPostTopicsData;

/**
 * Suggests the 10 most viral, on-brand blog topics for ONE chosen blog
 * category — the category is the niche, and its name/description drive both
 * the Tavily trend research and the agent prompt, on top of the company
 * profile. Read-only — never persists anything.
 */
interface PostTopicIdeatorPort
{
    /**
     * @return list<PostTopicIdeaData>
     */
    public function suggestTopics(SuggestPostTopicsData $data, ?object $causer = null): array;
}
