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
 * client, with `SpendGuardPort::ensure()` per attempt. Only the exception
 * class is logged, never payloads. Records which provider + model answered
 * (NFR-17, SC-19).
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
     * @param  class-string  $agentClass
     * @return array{response: mixed, provider: string, model: string|null}
     */
    #[\NoDiscard]
    public function call(AiPurpose $purpose, string $agentClass, string $prompt, int $userId, ?int $runId = null, ?CacheablePrompt $cached = null): array
    {
        $purposeConfig = (array) config("ai.purposes.{$purpose->value}", []);
        $lastException = null;

        if ($cached !== null) {
            app(PromptCacheScope::class)->set($cached);
        }

        try {
            foreach ($this->failover->attempts($purposeConfig['provider'] ?? null) as $provider) {
                try {
                    $this->spend->ensure('llm', $userId, $runId);

                    $response = $this->ai->generateStructured(
                        $agentClass,
                        $prompt,
                        $provider,
                        $purposeConfig['models'][$provider] ?? null,
                        $purposeConfig['timeout'] ?? null,
                    );

                    return ['response' => $response, 'provider' => $provider, 'model' => $purposeConfig['models'][$provider] ?? null];
                } catch (\Throwable $exception) {
                    $lastException = $exception;

                    if (! $this->policy->isRetryable($exception)) {
                        throw $exception;
                    }
                }
            }

            throw new AllProvidersFailedException(
                "All LLM providers failed for {$purpose->value}: ".($lastException !== null ? $lastException::class : 'unknown'),
            );
        } finally {
            if ($cached !== null) {
                app(PromptCacheScope::class)->clear();
            }
        }
    }
}
