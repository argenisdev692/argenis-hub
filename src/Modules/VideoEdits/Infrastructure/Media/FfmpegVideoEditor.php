<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Media;

use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Modules\VideoEdits\Domain\Exceptions\InvalidMediaException;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\Services\SilenceCutParser;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\SilenceThreshold;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The production {@see VideoEditorPort} — the only class in the system that
 * runs FFmpeg (spec §4.2 RENDER · plan T026).
 *
 * It drives the binaries directly rather than through `laravel-ffmpeg`'s
 * fluent API, for the three reasons research.md recorded: the package has no
 * multi-range cut API, no silence helper, and no way to pass a filtergraph as a
 * file — which a 200-cut render requires. It still reads that package's
 * `config/laravel-ffmpeg.php` for binary paths and the timeout, so there is one
 * place to configure FFmpeg in this application.
 *
 * Failures are deliberately **transient** ({@see RuntimeException}): a killed
 * process, a full disk or a busy worker are what FFmpeg usually fails on, and
 * those are worth the job's two retries. Media that is genuinely unusable is
 * caught upstream by the pipeline's container check, which knows the clip
 * position and can raise a permanent failure naming it.
 */
final readonly class FfmpegVideoEditor implements VideoEditorPort
{
    public function __construct(
        private FfmpegCommandBuilder $commands,
        private FilterGraphBuilder $graphs,
        private SilenceCutParser $silenceParser,
        private ConfigRepository $config,
        private LoggerInterface $logger,
    ) {}

    public function probe(string $path): MediaProbe
    {
        $process = $this->run($this->commands->probe($path));

        if (! $process->isSuccessful()) {
            // An unreadable file is not an infrastructure fault — it is a clip
            // the user uploaded. Reporting "no video" lets the pipeline fail it
            // permanently against the right clip position (FR-20).
            $this->logFailure('probe', $process);

            return new MediaProbe(0, '', hasVideo: false, hasAudio: false);
        }

        return self::toMediaProbe(
            json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR) ?: [],
        );
    }

    public function merge(
        array $inputPaths,
        array $inputProbes,
        string $outputPath,
        OutputProfile $profile,
        bool $intermediate,
        Closure $onProgress,
    ): void {
        $expectedMs = array_sum(array_map(
            static fn (MediaProbe $probe): int => $probe->durationMs,
            $inputProbes,
        ));

        $this->encode(
            $this->commands->merge(
                $inputPaths,
                $this->writeFilterGraph($outputPath, $this->graphs->merge($inputProbes, $profile)),
                $outputPath,
                $profile,
                $intermediate,
            ),
            $expectedMs,
            $onProgress,
            'merge',
        );
    }

    public function extractAudio(string $inputPath, string $outputPath, int $maxBytes): void
    {
        $process = $this->run(
            $this->commands->extractAudio(
                $inputPath,
                $outputPath,
                (int) $this->config->get('video-edit.speech.audio_sample_rate', 16_000),
                (int) $this->config->get('video-edit.speech.audio_bitrate_kbps', 32),
            ),
            $this->timeoutSeconds(),
        );

        if (! $process->isSuccessful()) {
            $this->logFailure('extractAudio', $process);

            throw new RuntimeException('Audio extraction failed.');
        }

        $size = @filesize($outputPath);

        // Retrying cannot shrink the file, so this is permanent rather than
        // transient: the user has to shorten the recording.
        if ($size === false || $size > $maxBytes) {
            throw InvalidMediaException::audioTooLargeToTranscribe((int) $size, $maxBytes);
        }
    }

    public function detectSilences(string $path, MediaProbe $probe, SilenceThreshold $threshold, int $noiseFloorDb): array
    {
        if (! $probe->hasAudio) {
            // A silent clip has no silences to remove — it is entirely one.
            // Returning nothing keeps the whole clip, which is what a user who
            // merged a screen recording without narration expects.
            return [];
        }

        $process = $this->run(
            $this->commands->detectSilences($path, $threshold, $noiseFloorDb),
            $this->timeoutSeconds(),
        );

        if (! $process->isSuccessful()) {
            $this->logFailure('detectSilences', $process);

            throw new RuntimeException('Silence detection failed.');
        }

        return $this->silenceParser->parse($process->getErrorOutput(), $probe->durationMs);
    }

    /**
     * @param  list<TimeRange>  $keepRanges
     */
    public function render(
        string $inputPath,
        MediaProbe $inputProbe,
        array $keepRanges,
        string $outputPath,
        OutputProfile $profile,
        Closure $onProgress,
    ): void {
        $expectedMs = array_sum(array_map(
            static fn (TimeRange $range): int => $range->durationMs(),
            $keepRanges,
        ));

        $this->encode(
            $this->commands->render(
                $inputPath,
                $this->writeFilterGraph($outputPath, $this->graphs->keepRanges($keepRanges, $inputProbe, $profile)),
                $outputPath,
                $profile,
            ),
            $expectedMs,
            $onProgress,
            'render',
        );
    }

    /**
     * Runs an encode, translating FFmpeg's `-progress` stream into the port's
     * 0–100 contract.
     *
     * @param  list<string>  $command
     * @param  Closure(int): void  $onProgress
     */
    private function encode(array $command, int $expectedDurationMs, Closure $onProgress, string $operation): void
    {
        $process = $this->run(
            $command,
            $this->timeoutSeconds(),
            function (string $type, string $buffer) use ($expectedDurationMs, $onProgress): void {
                if ($type === Process::OUT) {
                    $this->reportProgress($buffer, $expectedDurationMs, $onProgress);
                }
            },
        );

        if (! $process->isSuccessful()) {
            $this->logFailure($operation, $process);

            throw new RuntimeException(sprintf('FFmpeg %s failed.', $operation));
        }

        // FFmpeg's last progress line lands slightly before the muxer finishes,
        // so the stage is closed explicitly rather than left at 98%.
        $onProgress(100);
    }

    /**
     * @param  Closure(int): void  $onProgress
     */
    private function reportProgress(string $buffer, int $expectedDurationMs, Closure $onProgress): void
    {
        if ($expectedDurationMs <= 0) {
            return;
        }

        // `out_time` is the one unambiguous field: `out_time_ms` has carried
        // microseconds for years despite its name, and differs across builds.
        if (preg_match_all('/^out_time=(\d+):(\d{2}):(\d{2}(?:\.\d+)?)$/m', $buffer, $matches, PREG_SET_ORDER) === 0) {
            return;
        }

        $last = array_last($matches);
        $elapsedMs = (int) round(
            ((int) $last[1] * 3_600 + (int) $last[2] * 60 + (float) $last[3]) * 1_000,
        );

        $onProgress(max(0, min(100, intdiv($elapsedMs * 100, $expectedDurationMs))));
    }

    /**
     * The graph is written beside its output, inside the edit workspace that is
     * wiped when processing ends either way (FR-18).
     */
    private function writeFilterGraph(string $outputPath, string $graph): string
    {
        $path = $outputPath.'.filtergraph';

        if (file_put_contents($path, $graph) === false) {
            throw new RuntimeException('The filtergraph could not be written to the workspace.');
        }

        return $path;
    }

    /**
     * @param  list<string>  $command
     * @param  (Closure(string, string): void)|null  $onOutput
     */
    private function run(array $command, ?int $timeoutSeconds = null, ?Closure $onOutput = null): Process
    {
        $process = new Process($command, timeout: $timeoutSeconds);
        $process->run($onOutput);

        return $process;
    }

    private function timeoutSeconds(): int
    {
        return (int) $this->config->get('laravel-ffmpeg.timeout', 3_600);
    }

    /**
     * Diagnostics go to the log, never to the user (FR-20). Only the tail is
     * kept: FFmpeg's error is always in its last lines, and the rest is a
     * stream dump that would bury it.
     */
    private function logFailure(string $operation, Process $process): void
    {
        $this->logger->error('video-edit.ffmpeg_failed', [
            'operation' => $operation,
            'exit_code' => $process->getExitCode(),
            'stderr_tail' => mb_substr(trim($process->getErrorOutput()), -2_000),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload  decoded `ffprobe -print_format json`
     */
    private static function toMediaProbe(array $payload): MediaProbe
    {
        /** @var array<int, array<string, mixed>> $streams */
        $streams = is_array($payload['streams'] ?? null) ? $payload['streams'] : [];
        /** @var array<string, mixed> $format */
        $format = is_array($payload['format'] ?? null) ? $payload['format'] : [];

        $video = self::firstStreamOfType($streams, 'video');
        $audio = self::firstStreamOfType($streams, 'audio');

        return new MediaProbe(
            durationMs: (int) round(((float) ($format['duration'] ?? 0)) * 1_000),
            container: (string) ($format['format_name'] ?? ''),
            hasVideo: $video !== null,
            hasAudio: $audio !== null,
            width: isset($video['width']) ? (int) $video['width'] : null,
            height: isset($video['height']) ? (int) $video['height'] : null,
            frameRate: $video === null ? null : self::toFrameRate($video['avg_frame_rate'] ?? null),
            videoCodec: isset($video['codec_name']) ? (string) $video['codec_name'] : null,
            audioCodec: isset($audio['codec_name']) ? (string) $audio['codec_name'] : null,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $streams
     * @return array<string, mixed>|null
     */
    private static function firstStreamOfType(array $streams, string $type): ?array
    {
        $matching = array_values(array_filter(
            $streams,
            static fn (array $stream): bool => ($stream['codec_type'] ?? null) === $type,
        ));

        return $matching === [] ? null : array_first($matching);
    }

    /**
     * FFprobe reports frame rates as the rational `30000/1001`, and as `0/0`
     * for streams that have none.
     */
    private static function toFrameRate(mixed $rational): ?float
    {
        if (! is_string($rational) || ! str_contains($rational, '/')) {
            return null;
        }

        [$numerator, $denominator] = array_map(floatval(...), explode('/', $rational, 2));

        return $denominator > 0.0 && $numerator > 0.0 ? round($numerator / $denominator, 3) : null;
    }
}
