<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI\PromptCache;

/**
 * Carries the prompt being sent to the agent that is about to run, so the
 * agent's `providerOptions()` can emit provider-specific cache fields.
 *
 * `AIClientInterface` resolves agents by class name and forwards only a prompt
 * string, so this container singleton is the hand-off. It is set and cleared
 * around one synchronous call by {@see PromptCachingAIClient}; a worker handles
 * one job at a time, so calls cannot see each other's prompt.
 */
final class PromptCacheScope
{
    private ?CacheablePrompt $current = null;

    public function set(CacheablePrompt $prompt): void
    {
        $this->current = $prompt;
    }

    public function current(): ?CacheablePrompt
    {
        return $this->current;
    }

    public function clear(): void
    {
        $this->current = null;
    }
}
