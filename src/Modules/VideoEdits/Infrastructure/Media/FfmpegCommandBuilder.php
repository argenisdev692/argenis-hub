<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Media;

use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\SilenceThreshold;

/**
 * Builds the FFmpeg / FFprobe argument vectors, and nothing else.
 *
 * Pure by design: process execution lives in {@see FfmpegVideoEditor}, so every
 * command this project will ever run against a real binary is unit-tested
 * without one being installed (T026 — the binary is a deployment concern, the
 * argument order is a correctness concern).
 *
 * Two rules hold for every command here:
 *
 * - **Argument vectors, never strings.** Nothing is shell-interpolated, so a
 *   path can never become an argument (OWASP §3).
 * - **The filtergraph is passed as a file** via FFmpeg's `-/option file` form
 *   (AD-5). A 200-cut graph is far past the Windows 32 KiB command limit that
 *   `laravel-ffmpeg` issue #505 reports, and the file form has no limit.
 */
final readonly class FfmpegCommandBuilder
{
    public function __construct(
        private string $ffmpegBinary,
        private string $ffprobeBinary,
        private string $videoCodec,
        private string $audioCodec,
        private string $pixelFormat,
        private int $crf,
        private string $preset,
        private int $intermediateCrf,
        private string $intermediatePreset,
        private int $audioBitrateKbps,
        private int|false $threads = false,
    ) {}

    /**
     * Everything needed to build a {@see MediaProbe},
     * as JSON on stdout so nothing has to be scraped from human-readable output.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public function probe(string $path): array
    {
        return [
            $this->ffprobeBinary,
            '-v', 'error',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            '-i', $path,
        ];
    }

    /**
     * Speech-optimized audio for a transcription provider (V2).
     *
     * Mono 16 kHz is what speech models resample to anyway, and 32 kbps keeps a
     * 90-minute recording — this project's maximum (D2) — near 21 MB, under the
     * 25 MB OpenAI rejects above. Encoding at delivery quality here would
     * overshoot that limit and buy nothing: the model never hears the difference.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public function extractAudio(string $inputPath, string $outputPath, int $sampleRate, int $bitrateKbps): array
    {
        return [
            $this->ffmpegBinary,
            ...self::COMMON_FLAGS,
            '-i', $inputPath,
            '-vn',
            '-map', '0:a:0',
            '-ac', '1',
            '-ar', (string) $sampleRate,
            '-b:a', sprintf('%dk', $bitrateKbps),
            $outputPath,
        ];
    }

    /**
     * `silencedetect` writes its findings to stderr and needs no output file,
     * hence the null muxer. Only the first audio stream is analysed — the
     * output profile has already decided there is exactly one.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public function detectSilences(string $path, SilenceThreshold $threshold, int $noiseFloorDb): array
    {
        return [
            $this->ffmpegBinary,
            ...self::COMMON_FLAGS,
            '-i', $path,
            '-map', '0:a:0',
            '-af', sprintf('silencedetect=noise=%ddB:d=%s', $noiseFloorDb, self::number($threshold->seconds())),
            '-f', 'null',
            '-',
        ];
    }

    /**
     * Normalize-and-join. `$intermediate` selects the near-lossless preset the
     * render pass will re-encode from (AD-4), so the merge does not bake its own
     * compression artefacts into a file that gets encoded a second time.
     *
     * @param  list<string>  $inputPaths
     * @return list<string>
     */
    #[\NoDiscard]
    public function merge(
        array $inputPaths,
        string $filterGraphPath,
        string $outputPath,
        OutputProfile $profile,
        bool $intermediate,
    ): array {
        $inputs = [];

        foreach ($inputPaths as $path) {
            $inputs[] = '-i';
            $inputs[] = $path;
        }

        return [
            $this->ffmpegBinary,
            ...self::COMMON_FLAGS,
            ...self::PROGRESS_FLAGS,
            ...$inputs,
            '-/filter_complex', $filterGraphPath,
            ...$this->encodingFlags($profile, $intermediate),
            $outputPath,
        ];
    }

    /**
     * The cut pass: one input, a trim/concat graph, always a delivery encode.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public function render(
        string $inputPath,
        string $filterGraphPath,
        string $outputPath,
        OutputProfile $profile,
    ): array {
        return [
            $this->ffmpegBinary,
            ...self::COMMON_FLAGS,
            ...self::PROGRESS_FLAGS,
            '-i', $inputPath,
            '-/filter_complex', $filterGraphPath,
            ...$this->encodingFlags($profile, intermediate: false),
            $outputPath,
        ];
    }

    /**
     * `-nostdin` matters in a queue worker: without it FFmpeg competes with the
     * parent process for the terminal and can hang a job forever.
     *
     * @var list<string>
     */
    private const array COMMON_FLAGS = ['-hide_banner', '-nostdin', '-y'];

    /**
     * Machine-readable progress on stdout keeps stderr free for diagnostics.
     *
     * @var list<string>
     */
    private const array PROGRESS_FLAGS = ['-progress', 'pipe:1', '-nostats'];

    /**
     * @return list<string>
     */
    private function encodingFlags(OutputProfile $profile, bool $intermediate): array
    {
        $threads = $this->threads === false ? [] : ['-threads', (string) $this->threads];

        return [
            '-map', FilterGraphBuilder::VIDEO_OUTPUT,
            '-map', FilterGraphBuilder::AUDIO_OUTPUT,
            '-c:v', $this->videoCodec,
            '-preset', $intermediate ? $this->intermediatePreset : $this->preset,
            '-crf', (string) ($intermediate ? $this->intermediateCrf : $this->crf),
            '-pix_fmt', $this->pixelFormat,
            '-c:a', $this->audioCodec,
            '-b:a', sprintf('%dk', $this->audioBitrateKbps),
            '-ar', (string) $profile->audioSampleRate,
            '-ac', (string) $profile->audioChannels,
            // An intermediate is read back by FFmpeg, never streamed, so moving
            // its index to the front would only cost a second pass over the file.
            ...($intermediate ? [] : ['-movflags', '+faststart']),
            ...$threads,
        ];
    }

    /**
     * `%F` is locale-independent: a worker running under a comma-decimal locale
     * would otherwise emit `d=1,500` and FFmpeg would read a different filter.
     */
    private static function number(float $value): string
    {
        return sprintf('%.3F', $value);
    }
}
