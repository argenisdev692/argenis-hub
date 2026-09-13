<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI\PromptCache;

use Laravel\Ai\Enums\Lab;

/**
 * Provider-specific prompt-cache fields for an agent. Use it on any agent that
 * implements `Laravel\Ai\Contracts\HasProviderOptions` and is called through
 * {@see PromptCachingAIClient}.
 *
 * - Anthropic: `laravel/ai` sends `instructions()` as a plain `system` string
 *   and merges provider options over the request body, so `system` is replaced
 *   by text blocks carrying `cache_control` breakpoints: the instructions and
 *   long-lived layers with the long TTL, short-lived layers with the default.
 *   Anthropic allows at most 4 breakpoints per request; extra layers are merged
 *   into the last block.
 * - OpenAI: prefix caching is automatic; `prompt_cache_key` keeps related
 *   requests on the same cache.
 * - Gemini and others: implicit prefix caching, nothing to send.
 */
trait UsesPromptCache
{
    private const int ANTHROPIC_MAX_BREAKPOINTS = 4;

    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $prompt = app(PromptCacheScope::class)->current();

        if ($prompt === null || ! (bool) config('ai.prompt_cache.enabled', true)) {
            return [];
        }

        return match ($provider instanceof Lab ? $provider->value : strtolower($provider)) {
            'anthropic' => ['system' => $this->anthropicSystemBlocks($prompt)],
            'openai' => ['prompt_cache_key' => mb_substr($prompt->cacheKey, 0, 64)],
            default => [],
        };
    }

    /**
     * @return list<array{type: string, text: string, cache_control: array<string, string>}>
     */
    private function anthropicSystemBlocks(CacheablePrompt $prompt): array
    {
        $long = config('ai.prompt_cache.long_ttl', '1h') === '1h'
            ? ['type' => 'ephemeral', 'ttl' => '1h']
            : ['type' => 'ephemeral'];

        $blocks = [['type' => 'text', 'text' => (string) $this->instructions(), 'cache_control' => $long]];

        foreach ($prompt->nonEmptyLayers() as $layer) {
            $control = $layer->longLived ? $long : ['type' => 'ephemeral'];

            if (count($blocks) === self::ANTHROPIC_MAX_BREAKPOINTS) {
                $last = array_pop($blocks);
                $blocks[] = ['type' => 'text', 'text' => $last['text']."\n\n".$layer->text, 'cache_control' => $control];

                continue;
            }

            $blocks[] = ['type' => 'text', 'text' => $layer->text, 'cache_control' => $control];
        }

        return $blocks;
    }
}
