<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Modules\Post\Application\Commands\GeneratePostContentHandler;
use Modules\Post\Domain\Ports\PostContentGeneratorPort;
use Modules\Post\Domain\Ports\PostCoverImageRendererPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One TEXT draft returned by {@see PostContentGeneratorPort} — the cheap half
 * of a quality-loop attempt. It carries copy and a cover image CONCEPT, never
 * rendered artwork: the image is billed once, on the winning draft, by
 * {@see PostCoverImageRendererPort}.
 *
 * Scores are not here either — they are a different model's verdict, in
 * {@see PostEvaluationData}. Iteration metadata is added by
 * {@see GeneratePostContentHandler} when it assembles
 * {@see GeneratedPostContentData}.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PostContentDraftData extends Data
{
    /**
     * @param  array{title: string, visual: string}  $coverImageConcept
     * @param  array{primary_keyword: string, lsi_keywords: list<string>}  $seoAnalysis
     */
    public function __construct(
        public string $title,
        public string $content,
        public string $excerpt,
        public string $metaTitle,
        public string $metaDescription,
        public string $metaKeywords,
        public array $coverImageConcept,
        public array $seoAnalysis,
        public string $provider,
    ) {}

    /**
     * The plain text the judge scores: the draft as a reader would meet it,
     * with no schema noise and none of the writer's own reasoning. Built here
     * rather than in the evaluator adapter so the shape and the thing that
     * reads it stay together.
     */
    #[\NoDiscard]
    public function toScorableText(): string
    {
        return implode("\n\n", [
            "Title: {$this->title}",
            "Excerpt: {$this->excerpt}",
            "Body:\n{$this->content}",
            "Meta title: {$this->metaTitle}",
            "Meta description: {$this->metaDescription}",
            "Meta keywords: {$this->metaKeywords}",
            "Primary keyword: {$this->seoAnalysis['primary_keyword']}",
            'LSI keywords: '.implode(', ', $this->seoAnalysis['lsi_keywords']),
        ]);
    }
}
