<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Services\SilenceCutParser;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;

/**
 * @param  list<TimeRange>  $ranges
 * @return list<array{0: int, 1: int}>
 */
function silenceBounds(array $ranges): array
{
    return array_map(static fn (TimeRange $range): array => [$range->startMs, $range->endMs], $ranges);
}

it('reads silence intervals from real silencedetect output', function (): void {
    $stderr = <<<'TXT'
    Input #0, mov,mp4,m4a,3gp,3g2,mj2, from 'take-1.mp4':
      Duration: 00:01:00.00, start: 0.000000, bitrate: 1205 kb/s
    [silencedetect @ 0x55d5c8a2b440] silence_start: 3.50567
    [silencedetect @ 0x55d5c8a2b440] silence_end: 5.01 | silence_duration: 1.50433
    size=N/A time=00:00:30.00 bitrate=N/A speed= 120x
    [silencedetect @ 0x55d5c8a2b440] silence_start: 20
    [silencedetect @ 0x55d5c8a2b440] silence_end: 22.4995 | silence_duration: 2.4995
    TXT;

    expect(silenceBounds((new SilenceCutParser)->parse($stderr, 60_000)))->toBe([
        [3_506, 5_010],
        [20_000, 22_500],
    ]);
});

it('closes a trailing silence at the end of the media', function (): void {
    $lines = [
        '[silencedetect @ 0x1] silence_start: 57.25',
    ];

    expect(silenceBounds((new SilenceCutParser)->parse($lines, 60_000)))->toBe([[57_250, 60_000]]);
});

it('clamps negative starts and ends beyond the media', function (): void {
    $stderr = "[silencedetect @ 0x1] silence_start: -0.0213\n[silencedetect @ 0x1] silence_end: 1.2\n"
        ."[silencedetect @ 0x1] silence_start: 59.5\n[silencedetect @ 0x1] silence_end: 60.3";

    expect(silenceBounds((new SilenceCutParser)->parse($stderr, 60_000)))->toBe([[0, 1_200], [59_500, 60_000]]);
});

it('ignores output without silences', function (): void {
    expect((new SilenceCutParser)->parse("frame= 1500 fps=300\nvideo:0kB audio:0kB", 60_000))->toBe([]);
});
