<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Infrastructure\Ai;

use Modules\SocialMedia\Application\DTOs\ContentEvaluationData;
use Modules\SocialMedia\Application\DTOs\GeneratedSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\GenerateSocialMediaContentData;
use Modules\SocialMedia\Application\DTOs\ScoreResultData;
use Modules\SocialMedia\Application\DTOs\ScoreSetData;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentEvaluatorPort;
use Modules\SocialMedia\Domain\Services\ContentQualityEvaluator;
use Modules\SocialMedia\Infrastructure\Broadcasting\SocialMediaProgressNotifier;
use Shared\Infrastructure\AI\AIClientInterface;

/**
 * The quality gate's independent half: runs
 * {@see EvaluateSocialMediaContentAgent} on the provider configured as
 * `ai.default_for_evaluation`, deliberately NOT on the caller's writing
 * provider.
 *
 * Never cached. Two drafts are never the same text, and a stale verdict would
 * let a rewrite inherit the score of the draft it replaced — the exact bug
 * caching is supposed to avoid.
 */
final readonly class LaravelAiSocialMediaEvaluatorAdapter implements SocialMediaContentEvaluatorPort
{
    public function __construct(
        private AIClientInterface $ai,
        private ContentQualityEvaluator $evaluator,
        private SocialMediaProgressNotifier $progress,
    ) {}

    public function evaluate(
        string $contentUuid,
        GeneratedSocialMediaContentData $draft,
        GenerateSocialMediaContentData $data,
        int $iteration = 1,
        ?object $causer = null,
    ): ContentEvaluationData {
        $this->progress->notify(
            $causer,
            $contentUuid,
            'scoring',
            "Iteration {$iteration}: an independent model is scoring the draft…",
            70,
            $iteration,
        );

        $provider = $this->provider();
        $response = $this->ai->generateStructured(
            EvaluateSocialMediaContentAgent::class,
            $this->buildEvaluationPrompt($draft, $data),
            $provider,
        );

        /**
         * @var array{
         *     eeat_analysis: array{experience_signals: list<string>, expertise_signals: list<string>, authoritativeness_signals: list<string>, trustworthiness_signals: list<string>},
         *     optimization_suggestions: list<string>,
         *     ai_detection_risk: array{value: int, label: string, explanation: string}
         * } $verdict
         */
        $verdict = [
            'eeat_analysis' => (array) $response['eeat_analysis'],
            'optimization_suggestions' => (array) $response['optimization_suggestions'],
            'ai_detection_risk' => (array) $response['ai_detection_risk'],
        ];

        return new ContentEvaluationData(
            scores: $this->mapScores((array) $response['scores']),
            eeatAnalysis: $verdict['eeat_analysis'],
            optimizationSuggestions: $verdict['optimization_suggestions'],
            aiDetectionRisk: $verdict['ai_detection_risk'],
            evaluatorProvider: $provider,
        );
    }

    /**
     * Falls back to the application default only when no judge is configured
     * at all. When that fallback equals the writing provider the gate stops
     * being independent, so the value is meant to be set explicitly.
     */
    private function provider(): string
    {
        $configured = config('ai.default_for_evaluation');

        return is_string($configured) && $configured !== ''
            ? $configured
            : (string) config('ai.default', 'openai');
    }

    /**
     * The judge sees the BRIEF and the DRAFT, never the writer's reasoning or
     * its research notes — an argument for why the content is good is exactly
     * the influence an independent scorer must not receive.
     */
    private function buildEvaluationPrompt(
        GeneratedSocialMediaContentData $draft,
        GenerateSocialMediaContentData $data,
    ): string {
        return implode("\n\n", array_filter([
            'BRIEF',
            "Topic: {$data->topic}",
            $data->angle !== null ? "Angle: {$data->angle}" : null,
            $data->audience !== null ? "Target audience: {$data->audience}" : null,
            "Business goal: {$data->businessGoal}",
            "Brand voice: {$data->brandVoice}",
            "Funnel stage: {$data->funnelStage}",
            "Output language: {$data->language}",
            'DRAFT TO SCORE',
            $draft->toScorableText(),
            'Score this draft against every dimension in your instructions.',
        ]));
    }

    /**
     * Threshold pass/fail is decided in PHP by {@see ContentQualityEvaluator},
     * never by the model — a judge that also ruled on its own thresholds could
     * simply declare itself satisfied.
     *
     * @param  array<string, mixed>  $rawScores
     */
    private function mapScores(array $rawScores): ScoreSetData
    {
        $toResult = static function (string $key, array $raw): ScoreResultData {
            $value = (int) $raw['value'];
            $threshold = ContentQualityEvaluator::THRESHOLDS[$key];

            return new ScoreResultData(
                value: $value,
                threshold: $threshold,
                passes: $value >= $threshold,
                factors: array_map(static fn (mixed $v): int => (int) $v, (array) $raw['factors']),
                explanation: (string) $raw['explanation'],
            );
        };

        $results = array_map(
            static fn (string $key): ScoreResultData => $toResult($key, (array) $rawScores[$key]),
            array_combine(
                array_keys(ContentQualityEvaluator::THRESHOLDS),
                array_keys(ContentQualityEvaluator::THRESHOLDS),
            ),
        );

        $evaluation = $this->evaluator->evaluate(
            array_map(static fn (ScoreResultData $r): int => $r->value, $results),
        );

        return new ScoreSetData(
            humanWritingIndex: $results['human_writing_index'],
            viralityScore: $results['virality_score'],
            engagementScore: $results['engagement_score'],
            roiScore: $results['roi_score'],
            trendAlignment: $results['trend_alignment'],
            allScoresPass: $evaluation->allPass,
            overallAverage: $evaluation->overallAverage,
        );
    }
}
