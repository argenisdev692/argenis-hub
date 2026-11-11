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
 *   This form was introduced in FFmpeg 7.0 — 6.x answers `Unrecognized option
 *   '/filter_complex'` and exits 8, which is why the Dockerfile pins 9.0.2
 *   instead of using the distro package (Ubuntu 24.04 = 6.1.1).
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
     * First pass of the two-pass loudness normalisation: measure the input and
     * print the five numbers the linear second pass needs.
     *
     * Audio only (`-vn`) and decoded to the null muxer, so this is a read of the
     * soundtrack, not a second encode. `print_format=json` puts the block on
     * stderr; {@see FfmpegVideoEditor} parses it there.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public function measureLoudness(string $path, float $targetLufs, float $truePeakDbTp, ?float $loudnessRange): array
    {
        return [
            $this->ffmpegBinary,
            ...self::COMMON_FLAGS,
            '-nostats',
            '-i', $path,
            '-vn',
            '-map', '0:a:0',
            '-af', self::loudnormStats($targetLufs, $truePeakDbTp, $loudnessRange),
            '-f', 'null',
            '-',
        ];
    }

    /**
     * Measures an already-built audio graph instead of a raw input.
     *
     * The cut graph is the material that will actually be delivered, so its
     * measurement is the one the second pass must use: measuring the source
     * would include audio the cuts removed. The stats filter is the graph's own
     * tail, which is why no `-af` appears here — FFmpeg refuses a second audio
     * filterchain on an output already fed by `filter_complex`.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public function measureGraphLoudness(string $inputPath, string $filterGraphPath): array
    {
        return [
            $this->ffmpegBinary,
            ...self::COMMON_FLAGS,
            '-nostats',
            '-i', $inputPath,
            '-/filter_complex', $filterGraphPath,
            '-map', FilterGraphBuilder::AUDIO_OUTPUT,
            '-f', 'null',
            '-',
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
     * One chunk of a longer recording.
     *
     * `-ss` before `-i` seeks on the container, which is what makes this cheap:
     * the demuxer still has to walk to the point, but nothing before it is decoded.
     * `-t` caps the length so a chunk cannot run past the boundary it was asked
     * for, and re-encoding with the same sample rate, channel count and bitrate as
     * {@see self::extractAudio()} keeps a chunk indistinguishable from the slice of
     * the original it came from.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public function sliceAudio(string $inputPath, string $outputPath, int $startMs, int $durationMs, int $sampleRate, int $bitrateKbps): array
    {
        return [
            $this->ffmpegBinary,
            ...self::COMMON_FLAGS,
            '-ss', self::timestamp($startMs),
            '-i', $inputPath,
            '-t', self::timestamp($durationMs),
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
     * `freezedetect` on a downscaled copy: whether the screen moves does not
     * need 1080p, and a small frame keeps this extra pass cheap. Audio is
     * dropped for the same reason. A blinking caret changes too few pixels to
     * cross the noise floor, so it still reads as a still screen.
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public function detectFrozenFrames(string $path, int $noiseDb, float $minSeconds): array
    {
        return [
            $this->ffmpegBinary,
            ...self::COMMON_FLAGS,
            '-i', $path,
            '-map', '0:v:0',
            '-an',
            '-vf', sprintf('scale=480:-2,freezedetect=n=%ddB:d=%s', $noiseDb, self::number($minSeconds)),
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
     * The measurement filter, kept in one place so both passes agree on the
     * target and on the JSON format the parser expects.
     */
    private static function loudnormStats(float $targetLufs, float $truePeakDbTp, ?float $loudnessRange): string
    {
        return sprintf(
            'loudnorm=I=%s:TP=%s%s:print_format=json',
            self::number($targetLufs),
            self::number($truePeakDbTp),
            $loudnessRange === null ? '' : ':LRA='.self::number($loudnessRange),
        );
    }

    /**
     * `%F` is locale-independent: a worker running under a comma-decimal locale
     * would otherwise emit `d=1,500` and FFmpeg would read a different filter.
     */
    private static function number(float $value): string
    {
        return sprintf('%.3F', $value);
    }

    /**
     * Milliseconds → `HH:MM:SS.mmm`, the form both `-ss` and `-t` accept.
     *
     * Written out rather than passed as seconds so a chunk boundary lands on an
     * exact millisecond: the detector downstream reads word timings to the
     * millisecond, and a chunk whose start drifted would shift every timestamp in
     * it by the same drift.
     */
    private static function timestamp(int $milliseconds): string
    {
        $totalMs = max(0, $milliseconds);

        return sprintf(
            '%02d:%02d:%02d.%03d',
            intdiv($totalMs, 3_600_000),
            intdiv($totalMs % 3_600_000, 60_000),
            intdiv($totalMs % 60_000, 1_000),
            $totalMs % 1_000,
        );
    }
}
