<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Modules\Post\Domain\Ports\PostContentEvaluatorPort;
use Modules\Post\Domain\Services\PostContentQualityEvaluator;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One independent verdict on a {@see PostContentDraftData}, produced by
 * {@see PostContentEvaluatorPort}.
 *
 * `scores` holds exactly the five keys in
 * {@see PostContentQualityEvaluator::THRESHOLDS}; `explanations` is keyed the
 * same way and is fed verbatim into the next iteration's rewrite prompt, which
 * is why the judge is told to name the failing line rather than to be polite.
 *
 * `allScoresPass` / `overallAverage` are computed in PHP by the evaluator
 * service, never reported by the model — a judge allowed to rule on its own
 * thresholds could simply declare itself satisfied.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PostEvaluationData extends Data
{
    /**
     * @param  array<string, int>  $scores
     * @param  array<string, string>  $explanations
     * @param  array{experience_signals: list<string>, expertise_signals: list<string>, authoritativeness_signals: list<string>, trustworthiness_signals: list<string>}  $eeatAnalysis
     * @param  list<string>  $optimizationSuggestions
     */
    public function __construct(
        public array $scores,
        public array $explanations,
        public array $eeatAnalysis,
        public int $aiDetectionRisk,
        public array $optimizationSuggestions,
        public bool $allScoresPass,
        public int $overallAverage,
        public string $evaluatorProvider,
    ) {}
}
