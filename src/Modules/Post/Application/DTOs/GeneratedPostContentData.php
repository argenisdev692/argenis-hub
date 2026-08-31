<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Modules\Post\Domain\Enums\PostImageMode;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Full generated draft returned to the frontend AI-assist panel — the user
 * reviews/edits this before it is ever persisted via CreatePostHandler /
 * UpdatePostHandler (PostData carries the final, possibly-edited values).
 *
 * `imagePrompts` are always present (BrandPalette-locked background + content
 * layers) so the user can generate covers externally in every image mode —
 * including `none`, where no image call was billed. `imageMode` echoes back
 * what was actually rendered so the client never has to infer it from a null
 * `coverImagePath`. `qualityWarning` is true when the quality-loop exhausted
 * iterations without clearing every threshold.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class GeneratedPostContentData extends Data
{
    /**
     * @param  array{background: string, content: string}  $imagePrompts
     * @param  array<string, mixed>  $scores
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
        /** @var array{background: string, content: string} */
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
        public array $scores,
        public array $optimizationSuggestions,
        public array $seoAnalysis,
    ) {}
}
