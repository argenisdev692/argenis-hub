<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Modules\SocialMedia\Domain\Ports\SocialMediaAssetRendererPort;
use Modules\SocialMedia\Domain\Ports\SocialMediaContentGeneratorPort;
use Modules\SocialMedia\Infrastructure\Queue\GenerateSocialMediaContentJob;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One TEXT draft returned by {@see SocialMediaContentGeneratorPort} — the
 * cheap half of an attempt. It carries copy and image CONCEPTS, never rendered
 * artwork: image and voiceover files are produced once, on the winning draft,
 * by {@see SocialMediaAssetRendererPort} (see {@see self::withCoverAssets()}).
 *
 * Scores are not here either — they are a separate model's verdict, in
 * {@see ContentEvaluationData}. Iteration metadata (attempts taken, whether
 * the loop gave up) is added by {@see GenerateSocialMediaContentJob}.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class GeneratedSocialMediaContentData extends Data
{
    /**
     * @param  list<string>  $hashtags
     * @param  array<string, PlatformContentData>  $platforms
     * @param  list<array{source: string, relevance: string, key_insight: string, used_in: list<string>}>  $researchSources
     * @param  list<string>  $tavilyDataUsed
     */
    public function __construct(
        public string $headline,
        public string $body,
        public string $callToAction,
        public array $hashtags,
        public array $platforms,
        public ImageConceptData $coverImageConcept,
        public array $researchSources,
        public array $tavilyDataUsed,
        public string $provider,
        public string $coverImagePrompt = '',
        public ?string $coverImagePath = null,
        public ?string $coverImageUrl = null,
    ) {}

    /**
     * @param  array<string, PlatformContentData>  $platforms
     */
    #[\NoDiscard]
    public function withCoverAssets(
        array $platforms,
        string $coverImagePrompt,
        ?string $coverImagePath,
        ?string $coverImageUrl,
    ): self {
        return clone ($this, [
            'platforms' => $platforms,
            'coverImagePrompt' => $coverImagePrompt,
            'coverImagePath' => $coverImagePath,
            'coverImageUrl' => $coverImageUrl,
        ]);
    }

    /**
     * The plain text a judge scores: every platform variation plus the base
     * copy, with no schema noise. Built here rather than in the evaluator
     * adapter so the shape and the thing that reads it stay together.
     */
    #[\NoDiscard]
    public function toScorableText(): string
    {
        $platforms = array_map(
            static fn (PlatformContentData $p): string => strtoupper($p->platform)." ({$p->characterCount} chars):\n"
                .$p->adaptedContent
                .($p->threadTweets === [] ? '' : "\nThread: ".implode(' | ', $p->threadTweets))
                .($p->videoScript() === null ? '' : "\nVideo VO: ".$p->videoScript())
                ."\nHashtags: ".implode(' ', $p->hashtags),
            $this->platforms,
        );

        return implode("\n\n", [
            "Headline: {$this->headline}",
            "Body:\n{$this->body}",
            "Call to action: {$this->callToAction}",
            'Base hashtags: '.implode(' ', $this->hashtags),
            ...array_values($platforms),
        ]);
    }
}
