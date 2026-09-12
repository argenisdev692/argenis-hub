<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Tests\Support;

use Closure;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\SilenceThreshold;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Throwable;

/**
 * In-memory stand-in for the FFmpeg adapter: records every call, writes small
 * placeholder output files, and can be told to fail at a given operation.
 */
final class FakeVideoEditor implements VideoEditorPort
{
    /** @var array<string, MediaProbe> keyed by file basename */
    public array $probes = [];

    public MediaProbe $defaultProbe;

    /** @var list<TimeRange> */
    public array $silences = [];

    public ?string $failOn = null;

    public ?Throwable $failure = null;

    /** @var list<array{operation: string, arguments: array<string, mixed>}> */
    public array $calls = [];

    public function __construct()
    {
        $this->defaultProbe = new MediaProbe(60_000, 'mov,mp4,m4a,3gp,3g2,mj2', true, true, 1920, 1080, 30.0, 'h264', 'aac');
    }

    public function probe(string $path): MediaProbe
    {
        $this->record('probe', ['path' => $path]);

        return $this->probes[basename($path)] ?? $this->defaultProbe;
    }

    public function merge(array $inputPaths, array $inputProbes, string $outputPath, OutputProfile $profile, bool $intermediate, Closure $onProgress): void
    {
        $this->record('merge', compact('inputPaths', 'outputPath', 'profile', 'intermediate'));
        $this->write($outputPath, 'merged');
        $onProgress(100);
    }

    public function extractAudio(string $inputPath, string $outputPath, int $maxBytes): void
    {
        $this->record('extractAudio', compact('inputPath', 'outputPath', 'maxBytes'));
        $this->write($outputPath, 'audio');
    }

    public function detectSilences(string $path, MediaProbe $probe, SilenceThreshold $threshold, int $noiseFloorDb): array
    {
        $this->record('detectSilences', compact('path', 'threshold', 'noiseFloorDb'));

        return $this->silences;
    }

    public function render(string $inputPath, MediaProbe $inputProbe, array $keepRanges, string $outputPath, OutputProfile $profile, Closure $onProgress): void
    {
        $this->record('render', compact('inputPath', 'keepRanges', 'outputPath', 'profile'));
        $this->write($outputPath, 'rendered');
        $onProgress(100);
    }

    public function called(string $operation): bool
    {
        return $this->callsTo($operation) !== [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function callsTo(string $operation): array
    {
        return array_values(array_map(
            static fn (array $call): array => $call['arguments'],
            array_filter($this->calls, static fn (array $call): bool => $call['operation'] === $operation),
        ));
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function record(string $operation, array $arguments): void
    {
        $this->calls[] = ['operation' => $operation, 'arguments' => $arguments];

        if ($this->failOn === $operation && $this->failure !== null) {
            throw $this->failure;
        }
    }

    private function write(string $path, string $contents): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $contents);
    }
}
