<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Modules\Post\Application\Commands\GeneratePostContentHandler;
use Modules\Post\Domain\Enums\PostImageMode;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The finished package returned to the frontend AI-assist panel, assembled by
 * {@see GeneratePostContentHandler} from three separately-priced parts: the
 * winning {@see PostContentDraftData} (text), the independent
 * {@see PostEvaluationData} that scored it, and the single
 * {@see RenderedCoverImageData} render pass. The user reviews/edits this
 * before it is ever persisted via CreatePostHandler / UpdatePostHandler
 * (PostData carries the final, possibly-edited values).
 *
 * `imagePrompts` are always present (BrandPalette-locked background + content
 * layers) so the user can generate covers externally in every image mode —
 * including `none`, where no image call was billed. `imageMode` echoes back
 * what was actually rendered so the client never has to infer it from a null
 * `coverImagePath`. `qualityWarning` is true when the quality loop exhausted
 * its iterations without clearing every threshold; `evaluatorProvider` names
 * the model that decided that, which is deliberately not `provider`.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class GeneratedPostContentData extends Data
{
    /**
     * @param  array{background: string, content: string}  $imagePrompts
     * @param  array<string, int>  $scores
     * @param  array{experience_signals: list<string>, expertise_signals: list<string>, authoritativeness_signals: list<string>, trustworthiness_signals: list<string>}  $eeatAnalysis
     * @param  list<string>  $optimizationSuggestions
     * @param  array{primary_keyword: string, lsi_keywords: list<string>}  $seoAnalysis
     */
    public function __construct(
        public string $title,
        public string $content,
        public string $excerpt,
        public string $metaTitle,
        public string $metaDescription,
        public string $metaKeywords,
        public PostImageMode $imageMode,
        public ?string $coverImagePath,
        public ?string $coverImageUrl,
        public array $imagePrompts,
        public string $provider,
        public int $seoScore,
        public int $eeatScore,
        public int $viralityScore,
        public int $roiScore,
        public int $humanWritingIndex,
        public int $aiDetectionRisk,
        public bool $allScoresPass,
        public int $iterationsRequired,
        public bool $qualityWarning,
        public ?string $qualityWarningMessage,
        public int $overallScoreAvg,
        public array $scores,
        public array $eeatAnalysis,
        public array $optimizationSuggestions,
        public array $seoAnalysis,
        public string $evaluatorProvider,
    ) {}
}
