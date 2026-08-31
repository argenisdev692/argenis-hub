<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Modules\SocialMedia\Domain\Services\ContentQualityEvaluator;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The five quality-loop scores for one generation attempt, evaluated against
 * {@see ContentQualityEvaluator::THRESHOLDS}.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ScoreSetData extends Data
{
    public function __construct(
        public ScoreResultData $humanWritingIndex,
        public ScoreResultData $viralityScore,
        public ScoreResultData $engagementScore,
        public ScoreResultData $roiScore,
        public ScoreResultData $trendAlignment,
        public bool $allScoresPass,
        public int $overallAverage,
    ) {}

    /**
     * Flattened `score_key => value` map for {@see ContentQualityEvaluator}.
     *
     * @return array<string, int>
     */
    public function toThresholdMap(): array
    {
        return [
            'human_writing_index' => $this->humanWritingIndex->value,
            'virality_score' => $this->viralityScore->value,
            'engagement_score' => $this->engagementScore->value,
            'roi_score' => $this->roiScore->value,
            'trend_alignment' => $this->trendAlignment->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toExplanationMap(): array
    {
        return [
            'human_writing_index' => $this->humanWritingIndex->explanation,
            'virality_score' => $this->viralityScore->explanation,
            'engagement_score' => $this->engagementScore->explanation,
            'roi_score' => $this->roiScore->explanation,
            'trend_alignment' => $this->trendAlignment->explanation,
        ];
    }
}
