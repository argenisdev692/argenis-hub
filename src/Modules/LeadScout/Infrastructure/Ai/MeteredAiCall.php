<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Ai;

use Laravel\Ai\Responses\StructuredAgentResponse;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Domain\Ports\BudgetLedgerPort;
use Modules\LeadScout\Domain\Ports\PipelineLoggerPort;
use Shared\Infrastructure\AI\AIClientInterface;
use Throwable;

/**
 * One budgeted structured LLM call (spec FR-10, US-10): resolves provider and
 * model from the catalog, pre-flights the monthly AI budget with a planning
 * estimate, tries the primary then the fallback, and settles the ledger with
 * the real token usage. Failures go through the redacting pipeline logger —
 * provider errors can echo prompt fragments (OWASP LLM02).
 */
final readonly class MeteredAiCall
{
    public function __construct(
        private AIClientInterface $ai,
        private AiModelCatalogPort $catalog,
        private BudgetLedgerPort $budgets,
        private PipelineLoggerPort $logger,
    ) {}

    /**
     * @param  'extraction'|'drafting'  $purpose
     * @param  class-string  $agentClass
     * @return array{response: StructuredAgentResponse, provider: string, model: string}
     */
    #[\NoDiscard]
    public function generate(
        string $purpose,
        string $agentClass,
        string $prompt,
        ?string $provider,
        ?string $model,
        int $timeoutSeconds,
        int $estimatedPromptTokens,
        int $estimatedCompletionTokens,
    ): array {
        $resolved = $this->catalog->resolve($purpose, $provider, $model);

        $this->budgets->ensure(
            BudgetCategory::Ai,
            $this->priceMicros($resolved['model'], $estimatedPromptTokens, $estimatedCompletionTokens),
        );

        $attempts = [[$resolved['provider'], $resolved['model']]];

        if (is_string($resolved['fallback_provider']) && is_string($resolved['fallback_model'])) {
            $attempts[] = [$resolved['fallback_provider'], $resolved['fallback_model']];
        }

        $lastError = null;

        foreach ($attempts as [$attemptProvider, $attemptModel]) {
            try {
                $response = $this->ai->generateStructured($agentClass, $prompt, $attemptProvider, $attemptModel, $timeoutSeconds);
            } catch (Throwable $e) {
                $lastError = $e;
                $this->logger->pipelineWarning("ai_{$purpose}_failed", [
                    'provider' => $attemptProvider,
                    'model' => $attemptModel,
                    'error' => mb_substr($e->getMessage(), 0, 300),
                ]);

                continue;
            }

            $this->budgets->spend(BudgetCategory::Ai, $this->priceMicros(
                $attemptModel,
                $response->usage->promptTokens ?? 0,
                $response->usage->completionTokens ?? 0,
            ));

            return ['response' => $response, 'provider' => $attemptProvider, 'model' => $attemptModel];
        }

        throw $lastError ?? new \RuntimeException("AI {$purpose} failed without a fallback.");
    }

    /**
     * Price in USD micros from the catalog's per-MTok rates; unknown models
     * price at zero so a missing catalog entry never blocks the pipeline.
     */
    private function priceMicros(string $model, int $promptTokens, int $completionTokens): int
    {
        $rates = (array) (config('lead-scout.ai_catalog', [])[$model] ?? []);

        return (int) round(
            $promptTokens * (float) ($rates['input_per_mtok_usd'] ?? 0)
            + $completionTokens * (float) ($rates['output_per_mtok_usd'] ?? 0),
        );
    }
}
