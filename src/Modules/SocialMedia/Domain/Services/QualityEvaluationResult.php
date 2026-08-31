<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Domain\Services;

/**
 * Outcome of one {@see ContentQualityEvaluator::evaluate()} pass over a
 * generation attempt's five scores.
 */
final readonly class QualityEvaluationResult
{
    /**
     * @param  list<string>  $failingScores
     */
    public function __construct(
        public bool $allPass,
        public array $failingScores,
        public int $overallAverage,
    ) {}
}
