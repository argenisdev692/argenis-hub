<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Modules\Campaigns\Application\Commands\RunCampaignGenerationHandler;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The finished outcome of a whole generation run, assembled by
 * {@see RunCampaignGenerationHandler} from three separately-priced parts: the
 * winning {@see CampaignDraftData}, the independent judge's
 * {@see CampaignEvaluationData}, and the artwork rendered once for that
 * winner.
 *
 * Unlike the draft, this shape DOES carry the loop-level metadata — how many
 * attempts it took, whether the loop gave up — because it describes the run,
 * not a single attempt.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class GeneratedCampaignData extends Data
{
    /**
     * @param  list<string>  $hashtags
     * @param  list<string>  $leadFormQuestions
     * @param  list<string>  $targetingSuggestions
     * @param  array<string, PlatformCampaignContentData>  $platforms
     * @param  list<string>  $optimizationSuggestions
     * @param  list<array{source: string, relevance: string, key_insight: string, used_in: list<string>}>  $researchSources
     * @param  list<string>  $tavilyDataUsed
     * @param  array{value: int, label: string, explanation: string}  $aiDetectionRisk
     */
    public function __construct(
        public string $headline,
        public string $primaryText,
        public ?string $description,
        public string $callToAction,
        public array $hashtags,
        public array $leadFormQuestions,
        public array $targetingSuggestions,
        public array $platforms,
        public ?string $coverImagePath,
        public ?string $coverImageUrl,
        public ?string $coverImagePrompt,
        public CampaignScoreSetData $scores,
        public array $optimizationSuggestions,
        public array $researchSources,
        public array $tavilyDataUsed,
        public array $aiDetectionRisk,
        public string $provider,
        public string $evaluatorProvider,
        public int $iterationsRequired,
        public bool $qualityWarning,
        public ?string $qualityWarningMessage,
    ) {}

    /**
     * Folds the three phases of one run back into a single shape.
     */
    #[\NoDiscard]
    public static function fromRun(
        CampaignDraftData $draft,
        CampaignEvaluationData $evaluation,
        int $iterationsRequired,
        bool $qualityWarning,
        ?string $qualityWarningMessage,
    ): self {
        return new self(
            headline: $draft->headline,
            primaryText: $draft->primaryText,
            description: $draft->description,
            callToAction: $draft->callToAction,
            hashtags: $draft->hashtags,
            leadFormQuestions: $draft->leadFormQuestions,
            targetingSuggestions: $draft->targetingSuggestions,
            platforms: $draft->platforms,
            coverImagePath: $draft->coverImagePath,
            coverImageUrl: $draft->coverImageUrl,
            coverImagePrompt: $draft->coverImagePrompt,
            scores: $evaluation->scores,
            optimizationSuggestions: $evaluation->optimizationSuggestions,
            researchSources: $draft->researchSources,
            tavilyDataUsed: $draft->tavilyDataUsed,
            aiDetectionRisk: $evaluation->aiDetectionRisk,
            provider: $draft->provider,
            evaluatorProvider: $evaluation->evaluatorProvider,
            iterationsRequired: $iterationsRequired,
            qualityWarning: $qualityWarning,
            qualityWarningMessage: $qualityWarningMessage,
        );
    }
}
