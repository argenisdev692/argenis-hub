<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI\PromptCache;

use Illuminate\Contracts\Config\Repository as Config;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Psr\Log\LoggerInterface;
use Shared\Infrastructure\AI\AIClientInterface;
use Throwable;

/**
 * Structured generation with the prompt laid out for provider caching.
 *
 * Wraps {@see AIClientInterface} (breaker, provider switch) rather than
 * extending it, so existing callers and their test doubles are untouched.
 * The agent must implement `HasProviderOptions` via {@see UsesPromptCache}.
 *
 * For Anthropic the layers travel as cached system blocks and the user message
 * is only the tail; for every other provider the whole prompt is one message
 * whose prefix is identical across calls. Cache hits are logged from the
 * response usage, so savings are observable in production.
 */
final readonly class PromptCachingAIClient
{
    public function __construct(
        private AIClientInterface $ai,
        private PromptCacheScope $scope,
        private Config $config,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param  class-string  $agentClass
     */
    public function generateStructured(
        string $agentClass,
        CacheablePrompt $prompt,
        string $provider,
        ?string $model = null,
        ?int $timeoutSeconds = null,
        ?string $step = null,
    ): StructuredAgentResponse {
        $enabled = (bool) $this->config->get('ai.prompt_cache.enabled', true);

        if ($enabled && ! is_subclass_of($agentClass, HasProviderOptions::class)) {
            throw new \LogicException(sprintf('%s must implement HasProviderOptions (use UsesPromptCache) to be called with a cacheable prompt.', $agentClass));
        }

        $layersInSystem = $enabled && strtolower($provider) === 'anthropic';

        $this->scope->set($prompt);

        try {
            $response = $this->ai->generateStructured(
                $agentClass,
                $layersInSystem ? $prompt->tail : $prompt->asSingleMessage(),
                $provider,
                $model,
                $timeoutSeconds,
            );
        } finally {
            $this->scope->clear();
        }

        $this->logUsage($agentClass, $provider, $response, $model, $prompt->cacheKey, $step);

        return $response;
    }

    /**
     * Stream an agent with the same cacheable prefix as structured calls.
     *
     * The returned response is `Responsable`: return it directly from a
     * controller for an SSE preview. Provider options are resolved eagerly by
     * the SDK during `stream()`, so the scope is cleared before returning —
     * same lifecycle as {@see generateStructured}.
     *
     * @param  class-string  $agentClass
     */
    public function streamStructured(
        string $agentClass,
        CacheablePrompt $prompt,
        string $provider,
        ?string $model = null,
        ?int $timeoutSeconds = null,
        ?string $step = null,
    ): StreamableAgentResponse {
        $enabled = (bool) $this->config->get('ai.prompt_cache.enabled', true);

        if ($enabled && ! is_subclass_of($agentClass, HasProviderOptions::class)) {
            throw new \LogicException(sprintf('%s must implement HasProviderOptions (use UsesPromptCache) to be called with a cacheable prompt.', $agentClass));
        }

        $layersInSystem = $enabled && strtolower($provider) === 'anthropic';

        $this->scope->set($prompt);

        try {
            return app($agentClass)->stream(
                $layersInSystem ? $prompt->tail : $prompt->asSingleMessage(),
                provider: $provider,
                model: $model,
                timeout: $timeoutSeconds ?? 120,
            );
        } finally {
            $this->scope->clear();
        }
    }

    private function logUsage(string $agentClass, string $provider, StructuredAgentResponse $response, ?string $model = null, ?string $cacheKey = null, ?string $step = null): void
    {
        if (! (bool) $this->config->get('ai.prompt_cache.log_usage', true)) {
            return;
        }

        try {
            $usage = $response->usage;

            $this->logger->info('ai.prompt_cache.usage', [
                'agent' => class_basename($agentClass),
                'provider' => $provider,
                'model' => $model,
                'step' => $step,
                'cache_key' => $cacheKey,
                'input_tokens' => $usage->promptTokens ?? null,
                'cache_read_input_tokens' => $usage->cacheReadInputTokens ?? null,
                'cache_write_input_tokens' => $usage->cacheWriteInputTokens ?? null,
            ]);
        } catch (Throwable) {
            // Observability must never fail a generation.
        }
    }
}
