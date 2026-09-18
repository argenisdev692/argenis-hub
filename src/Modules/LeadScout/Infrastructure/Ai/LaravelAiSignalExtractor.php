<?php

declare(strict_types=1);

namespace Modules\LeadScout\Infrastructure\Ai;

use Illuminate\Support\Facades\Log;
use Modules\LeadScout\Domain\Enums\BudgetCategory;
use Modules\LeadScout\Domain\Ports\AiModelCatalogPort;
use Modules\LeadScout\Domain\ValueObjects\SignalKey;
use Modules\LeadScout\Infrastructure\Budgets\BudgetLedger;
use Shared\Infrastructure\AI\AIClientInterface;
use Throwable;

/**
 * Verified LLM extraction (spec FR-10, T056): the agent proposes, the code
 * disposes. A signal persists only when its key is allowlisted AND its
 * excerpt appears literally in the stored source text — anything else is
 * discarded and counted. Provider/model resolve from the extraction
 * catalog with fallback; real token usage prices the AI budget.
 */
final readonly class LaravelAiSignalExtractor
{
    public function __construct(
        private AIClientInterface $ai,
        private AiModelCatalogPort $catalog,
        private BudgetLedger $budgets,
    ) {}

    /**
     * @return array{signals: list<array{signal_key: string, nature: string, excerpt: string, confidence: int, source_url: string}>, discarded: int, provider: string, model: string, company_type: ?string, team_size_observed: ?int}
     */
    #[\NoDiscard]
    public function extract(
        string $prompt,
        string $sourceText,
        ?string $provider = null,
        ?string $model = null,
    ): array {
        $resolved = $this->catalog->resolve('extraction', $provider, $model);

        // Pre-flight against the monthly AI budget with the planning
        // estimate (~8k in / ~1k out); real usage settles the ledger after.
        $this->budgets->ensure(BudgetCategory::Ai, $this->estimateMicros($resolved['model']));

        $attempts = [
            [$resolved['provider'], $resolved['model']],
        ];

        if (is_string($resolved['fallback_provider']) && is_string($resolved['fallback_model'])) {
            $attempts[] = [$resolved['fallback_provider'], $resolved['fallback_model']];
        }

        $lastError = null;

        foreach ($attempts as [$attemptProvider, $attemptModel]) {
            try {
                $response = $this->ai->generateStructured(
                    ExtractCompanySignalsAgent::class,
                    $prompt,
                    $attemptProvider,
                    $attemptModel,
                    120,
                );

                $this->spend($attemptModel, $response->usage->promptTokens ?? 0, $response->usage->completionTokens ?? 0);

                return $this->verified($response, $sourceText, $attemptProvider, $attemptModel);
            } catch (Throwable $e) {
                $lastError = $e;
                Log::warning('lead-scout.signal_extraction_failed', [
                    'provider' => $attemptProvider,
                    'model' => $attemptModel,
                    'error' => mb_substr($e->getMessage(), 0, 300),
                ]);
            }
        }

        throw $lastError ?? new \RuntimeException('Signal extraction failed without a fallback.');
    }

    /**
     * @return array{signals: list<array{signal_key: string, nature: string, excerpt: string, confidence: int, source_url: string}>, discarded: int, provider: string, model: string, company_type: ?string, team_size_observed: ?int}
     */
    private function verified(mixed $response, string $sourceText, string $provider, string $model): array
    {
        $signals = [];
        $discarded = 0;

        foreach ((array) ($response['signals'] ?? []) as $candidate) {
            $key = (string) ($candidate['signal_key'] ?? '');
            $excerpt = (string) ($candidate['excerpt'] ?? '');

            if (! SignalKey::allowed($key) || $excerpt === '' || ! str_contains($sourceText, $excerpt)) {
                $discarded++;

                continue;
            }

            $nature = ($candidate['nature'] ?? '') === 'inference' ? 'inference' : 'fact';

            $signals[] = [
                'signal_key' => $key,
                'nature' => $nature,
                'excerpt' => mb_substr($excerpt, 0, 2000),
                'confidence' => max(0, min(100, (int) ($candidate['confidence'] ?? 50))),
                'source_url' => mb_substr((string) ($candidate['source_url'] ?? ''), 0, 2048),
            ];
        }

        $companyType = (string) ($response['company_type'] ?? '');
        $teamSize = $response['team_size_observed'] ?? null;

        return [
            'signals' => $signals,
            'discarded' => $discarded,
            'provider' => $provider,
            'model' => $model,
            'company_type' => in_array($companyType, ['software_agency', 'consultancy', 'product_company', 'recruiter', 'large_outsourcer', 'other'], true) ? $companyType : null,
            'team_size_observed' => is_numeric($teamSize) ? (int) $teamSize : null,
        ];
    }

    private function estimateMicros(string $model): int
    {
        $catalog = (array) config('lead-scout.ai_catalog', []);

        if (! isset($catalog[$model])) {
            return 0;
        }

        return (int) round(
            8000 * (float) ($catalog[$model]['input_per_mtok_usd'] ?? 0)
            + 1000 * (float) ($catalog[$model]['output_per_mtok_usd'] ?? 0),
        );
    }

    private function spend(string $model, int $promptTokens, int $completionTokens): void
    {
        $catalog = (array) config('lead-scout.ai_catalog', []);

        if (! isset($catalog[$model])) {
            return;
        }

        $micros = (int) round(
            $promptTokens * (float) ($catalog[$model]['input_per_mtok_usd'] ?? 0)
            + $completionTokens * (float) ($catalog[$model]['output_per_mtok_usd'] ?? 0),
        );

        $this->budgets->spend(BudgetCategory::Ai, $micros);
    }
}
