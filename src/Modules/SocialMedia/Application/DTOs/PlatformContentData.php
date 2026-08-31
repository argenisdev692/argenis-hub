<?php

declare(strict_types=1);

namespace Modules\SocialMedia\Application\DTOs;

use Modules\SocialMedia\Domain\Ports\SocialMediaAssetRendererPort;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One platform's adapted copy, its image concept, and — on TikTok / Instagram
 * Reels — the CapCut video package.
 *
 * `imagePath`, `imageUrl`, `voiceoverAudioPath` and `voiceoverAudioUrl` are
 * EMPTY while the quality loop runs. They are filled once, on the winning
 * draft only, by {@see SocialMediaAssetRendererPort} — see
 * {@see self::withRenderedAssets()}. `imagePrompt` is likewise filled at
 * render time so that what the user is shown is exactly the string that was
 * (or would have been) sent to the image model.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class PlatformContentData extends Data
{
    /**
     * @param  list<string>  $hashtags
     * @param  list<string>  $threadTweets
     */
    public function __construct(
        public string $platform,
        public string $adaptedContent,
        public int $characterCount,
        public array $hashtags,
        public ImageConceptData $imageConcept,
        public bool $isThread = false,
        public array $threadTweets = [],
        public ?VideoPackageData $videoPackage = null,
        public string $imagePrompt = '',
        public ?string $imagePath = null,
        public ?string $imageUrl = null,
        public ?string $voiceoverAudioPath = null,
        public ?string $voiceoverAudioUrl = null,
    ) {}

    /**
     * The clean, continuous voiceover script. An accessor, not a stored twin
     * of `videoPackage->cleanScript` — the two used to be persisted side by
     * side and could drift. The route is likewise read from
     * `imageConcept->route`, not duplicated at this level.
     */
    public function videoScript(): ?string
    {
        return $this->videoPackage?->cleanScript;
    }

    /**
     * Copy of this variation carrying the artwork the renderer produced. A
     * wither (`clone with`) rather than a setter — the DTO stays a value.
     */
    #[\NoDiscard]
    public function withRenderedAssets(
        string $imagePrompt,
        ?string $imagePath,
        ?string $imageUrl,
        ?string $voiceoverAudioPath,
        ?string $voiceoverAudioUrl,
    ): self {
        return clone ($this, [
            'imagePrompt' => $imagePrompt,
            'imagePath' => $imagePath,
            'imageUrl' => $imageUrl,
            'voiceoverAudioPath' => $voiceoverAudioPath,
            'voiceoverAudioUrl' => $voiceoverAudioUrl,
        ]);
    }
}
