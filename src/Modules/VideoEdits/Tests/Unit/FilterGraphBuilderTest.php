<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Modules\VideoEdits\Infrastructure\Media\FilterGraphBuilder;

function graphProfile(): OutputProfile
{
    return new OutputProfile(1920, 1080, 29.97, 48_000, 2);
}

it('normalizes and joins clips, generating silence for clips without audio', function (): void {
    $graph = (new FilterGraphBuilder)->merge([
        new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0),
        new MediaProbe(12_500, 'matroska,webm', true, false, 3840, 2160, 60.0),
    ], graphProfile());

    expect(explode(';', $graph))->toBe([
        '[0:v:0]scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=29.970,format=yuv420p[v0]',
        '[0:a:0]aresample=48000:async=1,aformat=sample_rates=48000:channel_layouts=stereo[a0]',
        '[1:v:0]scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=29.970,format=yuv420p[v1]',
        'anullsrc=channel_layout=stereo:sample_rate=48000,atrim=duration=12.500[a1]',
        '[v0][a0][v1][a1]concat=n=2:v=1:a=1[outv][outa]',
    ]);
});

it('keeps only the requested ranges with millisecond precision', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(0, 10_150), new TimeRange(11_850, 60_000)],
        new MediaProbe(60_000, 'mov,mp4', true, true, 1920, 1080, 29.97),
        graphProfile(),
    );

    expect(explode(';', $graph))->toBe([
        '[0:v:0]trim=start=0.000:end=10.150,setpts=PTS-STARTPTS[v0]',
        '[0:a:0]atrim=start=0.000:end=10.150,asetpts=PTS-STARTPTS[a0]',
        '[0:v:0]trim=start=11.850:end=60.000,setpts=PTS-STARTPTS[v1]',
        '[0:a:0]atrim=start=11.850:end=60.000,asetpts=PTS-STARTPTS[a1]',
        '[v0][a0][v1][a1]concat=n=2:v=1:a=1[cv][ca]',
        '[cv]scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=29.970,format=yuv420p[outv]',
        '[ca]aresample=48000:async=1,aformat=sample_rates=48000:channel_layouts=stereo[outa]',
    ]);
});

it('fills silent ranges when the input has no audio', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(2_000, 4_500)],
        new MediaProbe(10_000, 'mov,mp4', true, false, 1920, 1080, 30.0),
        new OutputProfile(1920, 1080, 30.0, 48_000, 1),
    );

    expect($graph)->toContain('anullsrc=channel_layout=mono:sample_rate=48000,atrim=duration=2.500[a0]')
        ->and($graph)->not->toContain('[0:a:0]');
});

it('formats numbers independently of the process locale', function (): void {
    $previous = setlocale(LC_NUMERIC, '0');
    setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'German_Germany.1252');

    try {
        $graph = (new FilterGraphBuilder)->keepRanges(
            [new TimeRange(1_500, 2_250)],
            new MediaProbe(5_000, 'mov,mp4', true, true, 1920, 1080, 25.0),
            graphProfile(),
        );
    } finally {
        setlocale(LC_NUMERIC, (string) $previous);
    }

    expect($graph)->toContain('trim=start=1.500:end=2.250')
        ->and($graph)->toContain('fps=29.970');
});
