<?php

declare(strict_types=1);

namespace Modules\Campaigns\Domain\Services;

/**
 * Pure threshold logic for the generation quality-loop (Infrastructure/Queue
 * GenerateCampaignJob). No Laravel/Eloquent dependency — a generation attempt
 * either clears every threshold or it doesn't, and the job decides what to do
 * about it (retry, keep-best-attempt, flag warning).
 *
 * `audience_fit_score` is the gatekeeper (mirrors SocialMedia's
 * human_writing_index / the reference prompt's local_market_fit) — a Meta ad
 * that scores well on virality/ROI but doesn't fit the stated audience/niche
 * (and geographic market when supplied) is a wasted budget regardless of how
 * good the copy reads. The average of all five is exposed to the user as the
 * campaign's "success probability".
 */
final readonly class CampaignQualityEvaluator
{
    /**
     * @var array<string, int>
     */
    public const array THRESHOLDS = [
        'audience_fit_score' => 75,
        'virality_score' => 70,
        'roi_potential_score' => 70,
        'lead_quality_score' => 70,
        'trend_relevance_score' => 70,
    ];

    public const int MAX_ITERATIONS = 5;

    /**
     * @param  array<string, int>  $scores
     */
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
     * Maps the overall average onto a human-facing success-probability label
     * for the review screen — the direct answer to "how likely is this
     * campaign to succeed".
     */
    public function successProbabilityLabel(int $overallAverage): string
    {
        return match (true) {
            $overallAverage >= 85 => 'very_high',
            $overallAverage >= 70 => 'high',
            $overallAverage >= 50 => 'medium',
            default => 'low',
        };
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
