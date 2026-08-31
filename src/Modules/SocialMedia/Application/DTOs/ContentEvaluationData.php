<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Modules\SocialMedia\Domain\Ports\SocialMediaContentEvaluatorPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The judge's verdict on one draft — everything that is an OPINION ABOUT the
 * content rather than the content itself: the five quality scores, the E-E-A-T
 * read, the AI-detection risk and the rewrite suggestions.
 *
 * It is a separate DTO from {@see GeneratedSocialMediaContentData} because a
 * separate model produces it ({@see SocialMediaContentEvaluatorPort}). Keeping
 * the two shapes fused is what allowed the writer to grade its own draft.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ContentEvaluationData extends Data
{
    /**
     * @param  array{experience_signals: list<string>, expertise_signals: list<string>, authoritativeness_signals: list<string>, trustworthiness_signals: list<string>}  $eeatAnalysis
     * @param  list<string>  $optimizationSuggestions
     * @param  array{value: int, label: string, explanation: string}  $aiDetectionRisk
     */
    public function __construct(
        public ScoreSetData $scores,
        public array $eeatAnalysis,
        public array $optimizationSuggestions,
        public array $aiDetectionRisk,
        public string $evaluatorProvider,
    ) {}
}
