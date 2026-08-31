<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Services;

/**
 * Pure threshold logic for the generation quality-loop (Infrastructure/Queue
 * GenerateSocialMediaContentJob). No Laravel/Eloquent dependency — a
 * generation attempt either clears every threshold or it doesn't, and the
 * job decides what to do about it (retry, keep-best-attempt, flag warning).
 *
 * `human_writing_index` is deliberately the same threshold Post's SEO agent
 * targets — content that reads as AI-written fails regardless of how well it
 * scores elsewhere.
 */
final readonly class ContentQualityEvaluator
{
    /**
     * @var array<string, int>
     */
    public const array THRESHOLDS = [
        'human_writing_index' => 75,
        'virality_score' => 70,
        'engagement_score' => 70,
        'roi_score' => 70,
        'trend_alignment' => 70,
    ];

    public const int MAX_ITERATIONS = 5;

    /**
     * @param  array<string, int>  $scores
     */
    #[\NoDiscard]
    public function evaluate(array $scores): QualityEvaluationResult
    {
        $failingScores = $this->failingScores($scores);

        $overallAverage = self::THRESHOLDS
            |> array_keys(...)
            |> (fn (array $keys): int => (int) round(
                array_sum(array_map(static fn (string $key): int => $scores[$key] ?? 0, $keys)) / count($keys)
            ));

        return new QualityEvaluationResult(
            allPass: $failingScores === [],
            failingScores: $failingScores,
            overallAverage: $overallAverage,
        );
    }

    /**
     * @param  array<string, int>  $scores
     * @param  array<string, string>  $explanations  score key => why it scored that way
     * @return list<array{score: string, current: int, target: int, gap: int, explanation: string}>
     */
    #[\NoDiscard]
    public function identifyWeaknesses(array $scores, array $explanations): array
    {
        return array_map(
            static fn (string $key): array => [
                'score' => $key,
                'current' => $scores[$key] ?? 0,
                'target' => self::THRESHOLDS[$key],
                'gap' => self::THRESHOLDS[$key] - ($scores[$key] ?? 0),
                'explanation' => $explanations[$key] ?? '',
            ],
            $this->failingScores($scores),
        );
    }

    /**
     * @param  array<string, int>  $scores
     * @return list<string>
     */
    private function failingScores(array $scores): array
    {
        return array_values(array_filter(
            array_keys(self::THRESHOLDS),
            static fn (string $key): bool => ($scores[$key] ?? 0) < self::THRESHOLDS[$key],
        ));
    }
}
