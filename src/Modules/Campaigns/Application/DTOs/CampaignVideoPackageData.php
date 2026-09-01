<?php

declare(strict_types=1);

namespace Modules\Campaigns\Application\DTOs;

use Modules\Campaigns\Domain\Enums\CampaignAdFormat;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * CapCut-ready Meta Reels/Stories video package (9:16, stage-aware 15–30s).
 * Nested under a platform variant when {@see CampaignAdFormat} is reel or
 * story.
 *
 * `cleanScript` is the continuous voiceover text. `voiceoverAudioPath` /
 * `voiceoverAudioUrl` hold the ElevenLabs render of it — produced once, for
 * the winning draft only, and null when TTS is unconfigured or failed. The
 * timeline is fully usable without audio, which is why synthesis is
 * best-effort rather than a hard dependency.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CampaignVideoPackageData extends Data
{
    /**
     * @param  list<CampaignVideoSceneData>  $scenes
     */
    public function __construct(
        public array $scenes,
        public string $cleanScript,
        public string $soundSuggestion,
        public int $targetDurationSeconds = 15,
        public string $creativeStyle = 'ugc_native',
        public ?string $voiceoverAudioPath = null,
        public ?string $voiceoverAudioUrl = null,
    ) {}

    #[\NoDiscard]
    public function withVoiceover(?string $audioPath, ?string $audioUrl): self
    {
        return clone ($this, [
            'voiceoverAudioPath' => $audioPath,
            'voiceoverAudioUrl' => $audioUrl,
        ]);
    }
}
