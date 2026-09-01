<?php

declare(strict_types=1);

namespace Modules\Post\Infrastructure\Ai;

use Illuminate\Support\Facades\Log;
use Modules\Post\Application\DTOs\GeneratePostContentData;
use Modules\Post\Application\DTOs\PostContentDraftData;
use Modules\Post\Application\DTOs\PostEvaluationData;
use Modules\Post\Domain\Enums\PostAiGenerationStatus;
use Modules\Post\Domain\Exceptions\PostGenerationUnavailableException;
use Modules\Post\Domain\Ports\PostContentEvaluatorPort;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Modules\Post\Infrastructure\Broadcasting\PostGenerationProgressReporter;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerOpenException;

/**
 * The quality gate's independent half: runs {@see EvaluatePostContentAgent} on
 * the provider configured as `ai.default_for_evaluation`, deliberately NOT on
 * the caller's writing provider.
 *
 * Never cached. Two drafts are never the same text, and a stale verdict would
 * let a rewrite inherit the score of the draft it replaced — the exact bug
 * caching is supposed to avoid.
 */
final readonly class LaravelAiPostEvaluatorAdapter implements PostContentEvaluatorPort
{
    public function __construct(
        private AIClientInterface $ai,
        private PostContentQualityEvaluator $evaluator,
        private PostGenerationProgressReporter $reporter,
    ) {}

    public function evaluate(
        ?string $generationUuid,
        PostContentDraftData $draft,
        GeneratePostContentData $data,
        int $iteration = 1,
        ?object $causer = null,
    ): PostEvaluationData {
        $this->reporter->report(
            $generationUuid,
            $causer,
            PostAiGenerationStatus::Judging,
            "Iteration {$iteration}: an independent model is scoring the draft…",
            $this->scoringProgress($iteration),
            $iteration,
        );

        $provider = $this->provider($data->provider);

        // Same boundary translation as the writer: the judge runs on its OWN
        // provider, so its breaker opens independently — a down judge must not
        // read as a down writer.
        try {
            $response = $this->ai->generateStructured(
                EvaluatePostContentAgent::class,
                $this->buildEvaluationPrompt($draft, $data),
                $provider,
            );
        } catch (CircuitBreakerOpenException $exception) {
            throw PostGenerationUnavailableException::forService($provider, $exception);
        }

        /** @var array<string, mixed> $rawScores */
        $rawScores = (array) $response['scores'];

        $scores = [];
        $explanations = [];

        foreach (array_keys(PostContentQualityEvaluator::THRESHOLDS) as $key) {
            /** @var array{value: int|string, explanation: string} $raw */
            $raw = (array) ($rawScores[$key] ?? ['value' => 0, 'explanation' => '']);
            $scores[$key] = (int) $raw['value'];
            $explanations[$key] = (string) ($raw['explanation'] ?? '');
        }

        $evaluation = $this->evaluator->evaluate($scores);

        /** @var array{experience_signals: list<string>, expertise_signals: list<string>, authoritativeness_signals: list<string>, trustworthiness_signals: list<string>} $eeatAnalysis */
        $eeatAnalysis = (array) $response['eeat_analysis'];

        return new PostEvaluationData(
            scores: $scores,
            explanations: $explanations,
            eeatAnalysis: $eeatAnalysis,
            aiDetectionRisk: (int) $response['ai_detection_risk'],
            optimizationSuggestions: (array) $response['optimization_suggestions'],
            allScoresPass: $evaluation->allPass,
            overallAverage: $evaluation->overallAverage,
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
            Log::warning('post.ai.evaluator_not_independent', [
                'provider' => $provider,
                'hint' => 'Set AI_EVALUATOR_PROVIDER to a provider other than the writing provider.',
            ]);
        }

        return $provider;
    }

    /**
     * The judge sees the BRIEF and the DRAFT, never the writer's reasoning or
     * its research notes — an argument for why the content is good is exactly
     * the influence an independent scorer must not receive.
     */
    private function buildEvaluationPrompt(PostContentDraftData $draft, GeneratePostContentData $data): string
    {
        return implode("\n\n", array_filter([
            'BRIEF',
            "Topic: {$data->topic}",
            $data->angle !== null ? "Angle: {$data->angle}" : null,
            $data->keyTrend !== null ? "Key trend the draft was asked to reference: {$data->keyTrend}" : null,
            'DRAFT TO SCORE',
            $draft->toScorableText(),
            'Score this draft against every dimension in your instructions.',
        ]));
    }

    /**
     * Scoring sits just after the write inside the same iteration's slice of
     * the 0-80% the loop owns (the render pass owns the rest).
     */
    private function scoringProgress(int $iteration): int
    {
        return (int) round((($iteration - 1) / PostContentQualityEvaluator::MAX_ITERATIONS) * 80) + 12;
    }
}
