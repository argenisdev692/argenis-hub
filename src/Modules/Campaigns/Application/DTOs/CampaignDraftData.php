<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Modules\Campaigns\Domain\Ports\CampaignGeneratorPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One TEXT-ONLY generation attempt returned by
 * {@see CampaignGeneratorPort} — copy, platform variants, image concepts and
 * the research the writer leaned on. No artwork, no voiceover: nothing here
 * costs more than a single text completion, which is what makes a rejected
 * attempt cheap enough to run five of.
 *
 * Scores are NOT on this shape. The writer no longer grades itself; an
 * independent judge produces {@see CampaignEvaluationData} from this draft.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CampaignDraftData extends Data
{
    /**
     * @param  list<string>  $hashtags
     * @param  list<string>  $leadFormQuestions
     * @param  list<string>  $targetingSuggestions
     * @param  array<string, PlatformCampaignContentData>  $platforms
     * @param  list<array{source: string, relevance: string, key_insight: string, used_in: list<string>}>  $researchSources
     * @param  list<string>  $tavilyDataUsed
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
        public CampaignImageConceptData $coverImageConcept,
        public array $researchSources,
        public array $tavilyDataUsed,
        public string $provider,
        public ?string $coverImagePath = null,
        public ?string $coverImageUrl = null,
        public ?string $coverImagePrompt = null,
    ) {}

    /**
     * Returns a copy carrying the artwork the renderer produced for this
     * draft. PHP 8.5 `clone with` — no `get_object_vars()` boilerplate, and
     * the DTO stays effectively immutable across the pipeline.
     *
     * @param  array<string, PlatformCampaignContentData>  $platforms
     */
    #[\NoDiscard]
    public function withRenderedAssets(
        array $platforms,
        ?string $coverImagePath,
        ?string $coverImageUrl,
        string $coverImagePrompt,
    ): self {
        return clone ($this, [
            'platforms' => $platforms,
            'coverImagePath' => $coverImagePath,
            'coverImageUrl' => $coverImageUrl,
            'coverImagePrompt' => $coverImagePrompt,
        ]);
    }

    /**
     * The exact text handed to the judge. It sees the AD, never the writer's
     * research notes or its argument for why the ad is good — an argument for
     * the content is precisely the influence an independent scorer must not
     * receive.
     */
    public function toScorableText(): string
    {
        $platformLines = array_map(
            static fn (PlatformCampaignContentData $p): string => implode("\n", [
                "[{$p->platform}] headline: {$p->headline}",
                "[{$p->platform}] primary text: {$p->adaptedPrimaryText}",
                "[{$p->platform}] hashtags: ".implode(' ', $p->hashtags),
                $p->videoPackage !== null
                    ? "[{$p->platform}] video script ({$p->videoPackage->targetDurationSeconds}s): {$p->videoPackage->cleanScript}"
                    : "[{$p->platform}] video: none",
            ]),
            array_values($this->platforms),
        );

        return implode("\n\n", array_filter([
            "Headline: {$this->headline}",
            "Primary text: {$this->primaryText}",
            $this->description !== null ? "Description: {$this->description}" : null,
            "Call to action: {$this->callToAction}",
            'Hashtags: '.implode(' ', $this->hashtags),
            'Lead form questions:'."\n".implode("\n", array_map(static fn (string $q): string => "- {$q}", $this->leadFormQuestions)),
            'Targeting suggestions:'."\n".implode("\n", array_map(static fn (string $t): string => "- {$t}", $this->targetingSuggestions)),
            'Platform variants:'."\n".implode("\n\n", $platformLines),
        ]));
    }
}
