<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One Meta surface's (Facebook or Instagram) adapted ad copy, its image
 * concept, and — once the quality loop has picked this draft — the rendered
 * artwork. Optional {@see CampaignVideoPackageData} is filled when the
 * campaign `ad_format` is reel or story.
 *
 * `imagePrompt`/`imagePath`/`imageUrl` are null while the draft is still a
 * candidate: nothing is billed until {@see CampaignAssetRendererPort} runs on
 * the winner. They stay null afterwards when the caller opted out via
 * `generate_images` or the provider call failed.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PlatformCampaignContentData extends Data
{
    /**
     * @param  list<string>  $hashtags
     */
    public function __construct(
        public string $platform,
        public string $adaptedPrimaryText,
        public int $characterCount,
        public string $headline,
        public ?string $description,
        public array $hashtags,
        public CampaignImageConceptData $imageConcept,
        public ?CampaignVideoPackageData $videoPackage = null,
        public ?string $imagePrompt = null,
        public ?string $imagePath = null,
        public ?string $imageUrl = null,
    ) {}

    /**
     * Returns a copy carrying what the renderer produced for this variant.
     * PHP 8.5 `clone with` — no `get_object_vars()` boilerplate.
     */
    #[\NoDiscard]
    public function withRenderedAssets(
        string $imagePrompt,
        ?string $imagePath,
        ?string $imageUrl,
        ?CampaignVideoPackageData $videoPackage,
    ): self {
        return clone ($this, [
            'imagePrompt' => $imagePrompt,
            'imagePath' => $imagePath,
            'imageUrl' => $imageUrl,
            'videoPackage' => $videoPackage,
        ]);
    }
}
