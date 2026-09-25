<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Infrastructure\Ai;

use Modules\CvJobStudio\Domain\Enums\AiPurpose;
use Modules\CvJobStudio\Domain\Exceptions\AllProvidersFailedException;
use Modules\CvJobStudio\Domain\Ports\SpendGuardPort;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\AI\PromptCache\CacheablePrompt;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;
use Shared\Infrastructure\AI\ProviderFailover;

/**
 * Every LLM call in the module runs through here (T-152, CHG-20): the
 * CourseScripts loop over [provider, ...fallbacks] through the Shared AI
 * client, with `SpendGuardPort::ensure()` before and `record()` after every
 * attempt, and the purpose's `max_input_chars` as a hard ceiling (LLM10).
 * Only the exception class is logged, never payloads. Records which
 * provider + model answered (NFR-17, SC-19).
 */
final readonly class AiCallExecutor
{
    public function __construct(
        private AIClientInterface $ai,
        private ProviderFailover $failover,
        private FailoverPolicy $policy,
        private SpendGuardPort $spend,
    ) {}

    /**
     * When `$cached` is given, the prompt scope is set for the whole attempt
     * loop (so `UsesPromptCache` agents emit provider cache fields) and
     * cleared afterwards — one synchronous call, no leakage across calls.
     *
     * Caching is opt-in twice over: the global `ai.prompt_cache.enabled` switch
     * and the purpose's own `prompt_cache` flag (CHG-20 decides it per purpose
     * — a one-off call is not worth an Anthropic cache-write premium). A prompt
     * handed in while the purpose opts out is still honoured, just sent whole.
     *
     * Anthropic carries the layers in cached system blocks, so only the tail
     * travels as the user message; every other provider gets the whole prompt
     * as one message whose prefix repeats. The `max_input_chars` ceiling is
     * applied to what is actually sent, so the untrusted tail is what it bounds.
     *
     * @param  class-string  $agentClass
     * @return array{response: mixed, provider: string, model: string|null}
     */
    #[\NoDiscard]
    public function call(AiPurpose $purpose, string $agentClass, string $prompt, int $userId, ?int $runId = null, ?CacheablePrompt $cached = null): array
    {
        $purposeConfig = (array) config("ai.purposes.{$purpose->value}", []);
        $maxInputChars = (int) ($purposeConfig['max_input_chars'] ?? 0);
        $callCostMicros = (int) round((float) config('cv-job-studio.llm_call_estimate_eur', 0) * 1_000_000);
        $lastException = null;

        $scope = $cached !== null
            && (bool) config('ai.prompt_cache.enabled', true)
            && (bool) ($purposeConfig['prompt_cache'] ?? false)
                ? app(PromptCacheScope::class)
                : null;

        $scope?->set($cached);

        try {
            foreach ($this->failover->attempts($purposeConfig['provider'] ?? null) as $provider) {
                // A spent budget is terminal for every provider — never failed over.
                $this->spend->ensure('llm', $userId, $runId);

                try {
                    $userMessage = $scope !== null && strtolower($provider) === 'anthropic'
                        ? $cached->tail
                        : $prompt;

                    if ($maxInputChars > 0) {
                        $userMessage = mb_substr($userMessage, 0, $maxInputChars);
                    }

                    $response = $this->ai->generateStructured(
                        $agentClass,
                        $userMessage,
                        $provider,
                        $purposeConfig['models'][$provider] ?? null,
                        $purposeConfig['timeout'] ?? null,
                    );

                    $this->spend->record('llm', $userId, $provider, $purpose->value, $callCostMicros, true, $runId);

                    return ['response' => $response, 'provider' => $provider, 'model' => $purposeConfig['models'][$provider] ?? null];
                } catch (\Throwable $exception) {
                    $lastException = $exception;
                    $this->spend->record('llm', $userId, $provider, $purpose->value, $callCostMicros, false, $runId);

                    if (! $this->policy->isRetryable($exception)) {
                        throw $exception;
                    }
                }
            }

            throw new AllProvidersFailedException(
                "All LLM providers failed for {$purpose->value}: ".($lastException !== null ? $lastException::class : 'unknown'),
            );
        } finally {
            $scope?->clear();
        }
    }
}
