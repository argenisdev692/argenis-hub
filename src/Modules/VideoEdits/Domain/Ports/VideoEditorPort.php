<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Ports;

use Closure;
use Modules\VideoEdits\Domain\Exceptions\InvalidMediaException;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\SilenceThreshold;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;

/**
 * The only component allowed to physically touch media (spec §4.2 RENDER).
 * All paths are local workspace files; the adapter never sees user input other
 * than validated numbers.
 */
interface VideoEditorPort
{
    public function probe(string $path): MediaProbe;

    /**
     * Normalize every clip to the output profile and join them in order.
     * `$intermediate` asks for a high-quality file that a render pass will re-encode (AD-4).
     *
     * @param  list<string>  $inputPaths
     * @param  list<MediaProbe>  $inputProbes  same order as $inputPaths
     * @param  Closure(int): void  $onProgress  0–100 within this operation
     */
    public function merge(
        array $inputPaths,
        array $inputProbes,
        string $outputPath,
        OutputProfile $profile,
        bool $intermediate,
        Closure $onProgress,
    ): void;

    /**
     * Extract the audio track as a compact speech-optimized file for
     * transcription (V2). Mono, low sample rate and low bitrate on purpose:
     * speech recognition gains nothing from stereo 48 kHz, and providers cap
     * the upload size.
     *
     * @throws InvalidMediaException when the result exceeds the provider limit
     */
    public function extractAudio(string $inputPath, string $outputPath, int $maxBytes): void;

    /**
     * @return list<TimeRange>
     */
    public function detectSilences(string $path, MediaProbe $probe, SilenceThreshold $threshold, int $noiseFloorDb): array;

    /**
     * Keep only `$keepRanges` (frame-accurate), in order, re-encoded to the profile.
     *
     * @param  list<TimeRange>  $keepRanges
     * @param  Closure(int): void  $onProgress  0–100 within this operation
     */
    public function render(
        string $inputPath,
        MediaProbe $inputProbe,
        array $keepRanges,
        string $outputPath,
        OutputProfile $profile,
        Closure $onProgress,
    ): void;
}
