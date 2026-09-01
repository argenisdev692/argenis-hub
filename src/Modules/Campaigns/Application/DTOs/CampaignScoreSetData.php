<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Modules\Campaigns\Domain\Services\CampaignQualityEvaluator;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The five quality-loop scores for one generation attempt, evaluated against
 * {@see CampaignQualityEvaluator::THRESHOLDS}. `overallAverage` IS the
 * "campaign success probability" surfaced to the user;
 * `successProbabilityLabel` (low/medium/high/very_high) is the human-facing
 * read of that number.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CampaignScoreSetData extends Data
{
    public function __construct(
        public CampaignScoreResultData $audienceFitScore,
        public CampaignScoreResultData $viralityScore,
        public CampaignScoreResultData $roiPotentialScore,
        public CampaignScoreResultData $leadQualityScore,
        public CampaignScoreResultData $trendRelevanceScore,
        public bool $allScoresPass,
        public int $overallAverage,
        public string $successProbabilityLabel,
    ) {}

    /**
     * Flattened `score_key => value` map for {@see CampaignQualityEvaluator}.
     *
     * @return array<string, int>
     */
    public function toThresholdMap(): array
    {
        return [
            'audience_fit_score' => $this->audienceFitScore->value,
            'virality_score' => $this->viralityScore->value,
            'roi_potential_score' => $this->roiPotentialScore->value,
            'lead_quality_score' => $this->leadQualityScore->value,
            'trend_relevance_score' => $this->trendRelevanceScore->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toExplanationMap(): array
    {
        return [
            'audience_fit_score' => $this->audienceFitScore->explanation,
            'virality_score' => $this->viralityScore->explanation,
            'roi_potential_score' => $this->roiPotentialScore->explanation,
            'lead_quality_score' => $this->leadQualityScore->explanation,
            'trend_relevance_score' => $this->trendRelevanceScore->explanation,
        ];
    }
}
