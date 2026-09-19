<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Ai;

use Illuminate\Support\Facades\Log;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Domain\Ports\DraftWriterPort;
use Modules\LeadScout\Infrastructure\Budgets\BudgetLedger;
use Shared\Infrastructure\AI\AIClientInterface;
use Throwable;

/**
 * Draft opener writer (spec US-10, T062): catalog-resolved provider/model
 * (or the selector override), fallback on failure, real usage priced.
 * Receives evidence + public proof summary only — never the CV (FR-35).
 */
final readonly class LaravelAiDraftWriter implements DraftWriterPort
{
    public function __construct(
        private AIClientInterface $ai,
        private AiModelCatalogPort $catalog,
        private BudgetLedger $budgets,
    ) {}

    /**
     * @return array{opener: string, provider: string, model: string}
     */
    #[\NoDiscard]
    public function write(
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
    ): array {
        $resolved = $this->catalog->resolve('drafting', $provider, $model);

        $this->budgets->ensure(BudgetCategory::Ai, $this->estimateMicros($resolved['model']));

        $attempts = [[$resolved['provider'], $resolved['model']]];

        if (is_string($resolved['fallback_provider']) && is_string($resolved['fallback_model'])) {
            $attempts[] = [$resolved['fallback_provider'], $resolved['fallback_model']];
        }

        $lastError = null;

        foreach ($attempts as [$attemptProvider, $attemptModel]) {
            try {
                $response = $this->ai->generateStructured(
                    WriteOutreachOpenerAgent::class,
                    $prompt,
                    $attemptProvider,
                    $attemptModel,
                    90,
                );

                $this->spend($attemptModel, $response->usage->promptTokens ?? 0, $response->usage->completionTokens ?? 0);

                $opener = trim((string) ($response['opener'] ?? ''));

                return [
                    'opener' => $opener !== '' ? mb_substr($opener, 0, 500) : 'I work with agencies like yours as a white-label Laravel contractor.',
                    'provider' => $attemptProvider,
                    'model' => $attemptModel,
                ];
            } catch (Throwable $e) {
                $lastError = $e;
                Log::warning('lead-scout.draft_generation_failed', [
                    'provider' => $attemptProvider,
                    'model' => $attemptModel,
                    'error' => mb_substr($e->getMessage(), 0, 300),
                ]);
            }
        }

        throw $lastError ?? new \RuntimeException('Draft generation failed without a fallback.');
    }

    private function estimateMicros(string $model): int
    {
        $catalog = (array) config('lead-scout.ai_catalog', []);

        if (! isset($catalog[$model])) {
            return 0;
        }

        return (int) round(
            4000 * (float) ($catalog[$model]['input_per_mtok_usd'] ?? 0)
            + 500 * (float) ($catalog[$model]['output_per_mtok_usd'] ?? 0),
        );
    }

    private function spend(string $model, int $promptTokens, int $completionTokens): void
    {
        $catalog = (array) config('lead-scout.ai_catalog', []);

        if (! isset($catalog[$model])) {
            return;
        }

        $this->budgets->spend(BudgetCategory::Ai, (int) round(
            $promptTokens * (float) ($catalog[$model]['input_per_mtok_usd'] ?? 0)
            + $completionTokens * (float) ($catalog[$model]['output_per_mtok_usd'] ?? 0),
        ));
    }
}
