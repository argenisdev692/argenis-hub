<?php

declare(strict_types=1);

namespace Modules\Campaigns\Infrastructure\Ai;

use Illuminate\Support\Facades\Log;
use Modules\Campaigns\Application\DTOs\CampaignDraftData;
use Modules\Campaigns\Application\DTOs\CampaignEvaluationData;
use Modules\Campaigns\Application\DTOs\CampaignScoreResultData;
use Modules\Campaigns\Application\DTOs\CampaignScoreSetData;
use Modules\Campaigns\Application\DTOs\GenerateCampaignData;
use Modules\Campaigns\Domain\Exceptions\CampaignGenerationUnavailableException;
use Modules\Campaigns\Domain\Ports\CampaignEvaluatorPort;
use Modules\Campaigns\Domain\Services\CampaignQualityEvaluator;
use Modules\Campaigns\Infrastructure\Broadcasting\CampaignProgressNotifier;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerOpenException;

/**
 * The quality gate's independent half: runs {@see EvaluateCampaignAgent} on
 * the provider configured as `ai.default_for_evaluation`, deliberately NOT on
 * the caller's writing provider.
 *
 * Never cached. Two drafts are never the same ad, and a stale verdict would
 * let a rewrite inherit the score of the draft it replaced — the exact bug
 * caching is supposed to avoid.
 */
final readonly class LaravelAiCampaignEvaluatorAdapter implements CampaignEvaluatorPort
{
    public function __construct(
        private AIClientInterface $ai,
        private CampaignQualityEvaluator $evaluator,
        private CampaignProgressNotifier $progress,
    ) {}

    public function evaluate(
        string $campaignUuid,
        CampaignDraftData $draft,
        GenerateCampaignData $data,
        int $iteration = 1,
        ?object $causer = null,
    ): CampaignEvaluationData {
        $this->progress->notify(
            $causer,
            $campaignUuid,
            'judging',
            "Iteration {$iteration}: an independent model is scoring the ad…",
            $this->scoringProgress($iteration),
            $iteration,
        );

        $provider = $this->provider($data->provider);

        // Same boundary translation as the writer: the judge runs on its OWN
        // provider, so its breaker opens independently — a down judge must not
        // read as a down writer.
        try {
            $response = $this->ai->generateStructured(
                EvaluateCampaignAgent::class,
                $this->buildEvaluationPrompt($draft, $data),
                $provider,
            );
        } catch (CircuitBreakerOpenException $exception) {
            throw CampaignGenerationUnavailableException::forService($provider, $exception);
        }

        /** @var array<string, mixed> $rawScores */
        $rawScores = (array) $response['scores'];

        $results = [];
        $values = [];
        $explanations = [];

        foreach (array_keys(CampaignQualityEvaluator::THRESHOLDS) as $key) {
            /** @var array{value: int|string, factors?: array<string, mixed>, explanation?: string} $raw */
            $raw = (array) ($rawScores[$key] ?? []);

            $value = (int) ($raw['value'] ?? 0);
            $threshold = CampaignQualityEvaluator::THRESHOLDS[$key];

            $values[$key] = $value;
            $explanations[$key] = (string) ($raw['explanation'] ?? '');

            $results[$key] = new CampaignScoreResultData(
                value: $value,
                threshold: $threshold,
                passes: $value >= $threshold,
                factors: array_map(static fn (mixed $v): int => (int) $v, (array) ($raw['factors'] ?? [])),
                explanation: $explanations[$key],
            );
        }

        $evaluation = $this->evaluator->evaluate($values);

        /** @var array{value: int, label: string, explanation: string} $aiDetectionRisk */
        $aiDetectionRisk = (array) $response['ai_detection_risk'];

        return new CampaignEvaluationData(
            scores: new CampaignScoreSetData(
                audienceFitScore: $results['audience_fit_score'],
                viralityScore: $results['virality_score'],
                roiPotentialScore: $results['roi_potential_score'],
                leadQualityScore: $results['lead_quality_score'],
                trendRelevanceScore: $results['trend_relevance_score'],
                allScoresPass: $evaluation->allPass,
                overallAverage: $evaluation->overallAverage,
                successProbabilityLabel: $this->evaluator->successProbabilityLabel($evaluation->overallAverage),
            ),
            explanations: $explanations,
            optimizationSuggestions: (array) $response['optimization_suggestions'],
            aiDetectionRisk: $aiDetectionRisk,
            evaluatorProvider: $provider,
        );
    }

    /**
     * Falls back to the application default only when no judge is configured
     * at all. When the judge resolves to the SAME provider the caller writes
     * with, the gate stops being independent and starts marking its own
     * homework — that is a misconfiguration, so it is logged rather than
     * silently tolerated.
     */
    private function provider(string $writingProvider): string
    {
        $configured = config('ai.default_for_evaluation');

        $provider = is_string($configured) && $configured !== ''
            ? $configured
            : (string) config('ai.default', 'openai');

        if ($provider === $writingProvider) {
            Log::warning('campaigns.ai.evaluator_not_independent', [
                'provider' => $provider,
                'hint' => 'Set AI_EVALUATOR_PROVIDER to a provider other than the writing provider.',
            ]);
        }

        return $provider;
    }

    /**
     * The judge sees the BRIEF and the AD, never the writer's reasoning or its
     * research notes — an argument for why the copy is good is exactly the
     * influence an independent scorer must not receive.
     */
    private function buildEvaluationPrompt(CampaignDraftData $draft, GenerateCampaignData $data): string
    {
        $geoLabel = implode(', ', array_values(array_filter(
            [$data->city, $data->state, $data->country],
            static fn (?string $part): bool => $part !== null && $part !== '',
        )));

        return implode("\n\n", array_filter([
            'BRIEF',
            "Topic: {$data->topic}",
            $data->niche !== null ? "Niche: {$data->niche}" : null,
            $data->audience !== null ? "Target audience: {$data->audience}" : null,
            $data->keyTrend !== null ? "Key trend the ad was asked to reference: {$data->keyTrend}" : null,
            $geoLabel !== '' ? "Geographic market: {$geoLabel}" : null,
            "Business goal: {$data->businessGoal}",
            "Funnel stage: {$data->funnelStage}",
            "Meta platform: {$data->platform}",
            "Ad format: {$data->adFormat}",
            'AD TO SCORE',
            $draft->toScorableText(),
            'Score this ad against every dimension in your instructions.',
        ]));
    }

    /**
     * Scoring sits just after the write inside the same iteration's slice of
     * the 0-80% the loop owns (the render pass owns the rest).
     */
    private function scoringProgress(int $iteration): int
    {
        return (int) round((($iteration - 1) / CampaignQualityEvaluator::MAX_ITERATIONS) * 80) + 12;
    }
}
