<?php

declare(strict_types=1);

namespace Modules\Post\Application\DTOs;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Full Reel/TikTok package returned to the frontend AI-assist panel:
 * scene-by-scene timeline, clean voice-over script, sound cue, TikTok
 * caption/hashtags and — when ElevenLabs synthesis succeeds — a stored
 * voiceover audio URL. `voiceoverAudioUrl` is null when synthesis was
 * unavailable/failed; the script/timeline are still fully usable without it.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class ReelPackageData extends Data
{
    /**
     * @param  list<ReelSceneData>  $scenes
     * @param  list<string>  $tiktokHashtags
     */
    public function __construct(
        public array $scenes,
        public string $cleanScript,
        public string $soundSuggestion,
        public string $tiktokCaption,
        public array $tiktokHashtags,
        public ?string $voiceoverAudioUrl,
        public int $targetDurationSeconds = 15,
        public string $creativeStyle = 'ugc_native',
    ) {}
}
