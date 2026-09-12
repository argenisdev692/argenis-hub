<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\SilenceThreshold;
use Modules\VideoEdits\Infrastructure\Media\FfmpegCommandBuilder;

/**
 * The FFmpeg binary is a deployment concern (T001); the argument order is a
 * correctness concern, so it is tested here without one installed.
 */
function commandBuilder(int|false $threads = false): FfmpegCommandBuilder
{
    return new FfmpegCommandBuilder(
        ffmpegBinary: 'ffmpeg',
        ffprobeBinary: 'ffprobe',
        videoCodec: 'libx264',
        audioCodec: 'aac',
        pixelFormat: 'yuv420p',
        crf: 20,
        preset: 'medium',
        intermediateCrf: 14,
        intermediatePreset: 'veryfast',
        audioBitrateKbps: 192,
        threads: $threads,
    );
}

function profile(): OutputProfile
{
    return new OutputProfile(1920, 1080, 30.0, 48_000, 2);
}

/**
 * @param  list<string>  $command
 */
function valueAfter(array $command, string $flag): ?string
{
    $index = array_search($flag, $command, true);

    return $index === false ? null : ($command[$index + 1] ?? null);
}

it('asks ffprobe for machine-readable stream and format data', function (): void {
    $command = commandBuilder()->probe('/w/source-1.mp4');

    expect($command[0])->toBe('ffprobe')
        ->and($command)->toContain('-show_streams', '-show_format')
        ->and(valueAfter($command, '-print_format'))->toBe('json')
        ->and(valueAfter($command, '-i'))->toBe('/w/source-1.mp4');
});

it('builds a silencedetect filter from the threshold and noise floor', function (): void {
    $threshold = SilenceThreshold::fromSeconds(1.5, 0.3, 10.0);

    $command = commandBuilder()->detectSilences('/w/merged.mp4', $threshold, -30);

    expect(valueAfter($command, '-af'))->toBe('silencedetect=noise=-30dB:d=1.500')
        ->and(valueAfter($command, '-f'))->toBe('null')
        ->and(array_last($command))->toBe('-');
});

it('formats the silence duration locale-independently', function (): void {
    $locale = setlocale(LC_NUMERIC, '0');
    setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'German_Germany');

    $command = commandBuilder()->detectSilences(
        '/w/merged.mp4',
        SilenceThreshold::fromSeconds(1.5, 0.3, 10.0),
        -30,
    );

    setlocale(LC_NUMERIC, $locale === false ? 'C' : $locale);

    // A comma here would silently change which silences FFmpeg removes.
    expect(valueAfter($command, '-af'))->toContain('d=1.500');
});

it('passes one -i per input and loads the filtergraph from a file', function (): void {
    $command = commandBuilder()->merge(
        ['/w/source-1.mp4', '/w/source-2.mp4', '/w/source-3.mp4'],
        '/w/merged.mp4.filtergraph',
        '/w/merged.mp4',
        profile(),
        intermediate: false,
    );

    expect(array_count_values($command)['-i'])->toBe(3)
        ->and(valueAfter($command, '-/filter_complex'))->toBe('/w/merged.mp4.filtergraph')
        ->and($command)->not->toContain('-filter_complex')
        ->and(array_last($command))->toBe('/w/merged.mp4');
});

it('maps the labelled outputs the filtergraph builder produces', function (): void {
    $command = commandBuilder()->render('/w/merged.mp4', '/w/out.mp4.filtergraph', '/w/out.mp4', profile());

    expect($command)->toContain('[outv]', '[outa]')
        ->and(valueAfter($command, '-ar'))->toBe('48000')
        ->and(valueAfter($command, '-ac'))->toBe('2')
        ->and(valueAfter($command, '-b:a'))->toBe('192k');
});

it('encodes an intermediate near-losslessly and skips the streaming index', function (): void {
    $intermediate = commandBuilder()->merge(['/w/a.mp4'], '/w/g', '/w/m.mp4', profile(), intermediate: true);
    $delivery = commandBuilder()->merge(['/w/a.mp4'], '/w/g', '/w/m.mp4', profile(), intermediate: false);

    expect(valueAfter($intermediate, '-crf'))->toBe('14')
        ->and(valueAfter($intermediate, '-preset'))->toBe('veryfast')
        ->and($intermediate)->not->toContain('-movflags')
        ->and(valueAfter($delivery, '-crf'))->toBe('20')
        ->and(valueAfter($delivery, '-preset'))->toBe('medium')
        ->and(valueAfter($delivery, '-movflags'))->toBe('+faststart');
});

it('requests machine-readable progress on every encode but not on probing', function (): void {
    $render = commandBuilder()->render('/w/in.mp4', '/w/g', '/w/out.mp4', profile());

    expect(valueAfter($render, '-progress'))->toBe('pipe:1')
        ->and($render)->toContain('-nostats', '-nostdin')
        ->and(commandBuilder()->probe('/w/in.mp4'))->not->toContain('-progress');
});

it('only pins a thread count when one is configured', function (): void {
    expect(commandBuilder(threads: false)->render('/w/i', '/w/g', '/w/o', profile()))
        ->not->toContain('-threads');

    expect(valueAfter(commandBuilder(threads: 4)->render('/w/i', '/w/g', '/w/o', profile()), '-threads'))
        ->toBe('4');
});

it('never interpolates a path into a shell string', function (): void {
    $hostile = '/w/source; rm -rf ~.mp4';

    $command = commandBuilder()->merge([$hostile], '/w/g', '/w/out.mp4', profile(), intermediate: false);

    // The path survives as exactly one argv entry, so no shell ever splits it.
    expect($command)->toContain($hostile);
});
