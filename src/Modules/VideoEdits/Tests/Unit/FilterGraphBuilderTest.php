<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\ValueObjects\CompressedRange;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\RenderEffects;
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
        '[0:a:0]atrim=start=0.000:end=10.150,asetpts=PTS-STARTPTS,afade=t=out:st=10.135:d=0.015[a0]',
        '[0:v:0]trim=start=11.850:end=60.000,setpts=PTS-STARTPTS[v1]',
        '[0:a:0]atrim=start=11.850:end=60.000,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.015[a1]',
        '[v0][a0][v1][a1]concat=n=2:v=1:a=1[cv][ca]',
        '[cv]scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=29.970,format=yuv420p[outv]',
        '[ca]aresample=48000:async=1,aformat=sample_rates=48000:channel_layouts=stereo[outa]',
    ]);
});

it('fades both sides of an inner range and never the outer edges of the recording', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(0, 1_000), new TimeRange(2_000, 3_000), new TimeRange(4_000, 5_000)],
        new MediaProbe(5_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
    );

    expect($graph)->toContain('atrim=start=0.000:end=1.000,asetpts=PTS-STARTPTS,afade=t=out:st=0.985:d=0.015[a0]')
        ->and($graph)->toContain('asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.015,afade=t=out:st=0.985:d=0.015[a1]')
        ->and($graph)->toContain('atrim=start=4.000:end=5.000,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.015[a2]');
});

it('does not fade a single kept range, which has no join', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(1_000, 4_000)],
        new MediaProbe(5_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
    );

    expect($graph)->not->toContain('afade');
});

it('plays a tool wait faster, picture and room tone together', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(0, 20_000)],
        new MediaProbe(20_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
        new RenderEffects([new CompressedRange(new TimeRange(5_000, 14_000), 3.0)]),
    );

    expect(array_slice(explode(';', $graph), 0, 6))->toBe([
        '[0:v:0]trim=start=0.000:end=5.000,setpts=PTS-STARTPTS[v0]',
        '[0:a:0]atrim=start=0.000:end=5.000,asetpts=PTS-STARTPTS,afade=t=out:st=4.985:d=0.015[a0]',
        '[0:v:0]trim=start=5.000:end=14.000,setpts=(PTS-STARTPTS)/3.000[v1]',
        '[0:a:0]atrim=start=5.000:end=14.000,asetpts=PTS-STARTPTS,atempo=3.000,afade=t=in:st=0:d=0.015,afade=t=out:st=2.985:d=0.015[a1]',
        '[0:v:0]trim=start=14.000:end=20.000,setpts=PTS-STARTPTS[v2]',
        '[0:a:0]atrim=start=14.000:end=20.000,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.015[a2]',
    ])->and($graph)->toContain('concat=n=3:v=1:a=1[cv][ca]');
});

it('turns breaths down with ramps, in segment-local time', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(0, 10_000), new TimeRange(12_000, 20_000)],
        new MediaProbe(20_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
        new RenderEffects([], [new TimeRange(13_000, 13_500)], -20.0),
    );

    // −20 dB is a gain of 0.1, so the curve dips by 0.9 between 1.0 s and 1.5 s
    // of the second segment, with 60 ms ramps.
    expect(explode(';', $graph)[3])->toBe(
        "[0:a:0]atrim=start=12.000:end=20.000,asetpts=PTS-STARTPTS,volume=volume='1-0.9000*clip(min((t-1.000)/0.060,(1.500-t)/0.060),0,1)':eval=frame,afade=t=in:st=0:d=0.015[a1]",
    )->and(explode(';', $graph)[1])->not->toContain('volume=');
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

it('leaves the audio chain untouched when room tone is not configured', function (): void {
    $graph = (new FilterGraphBuilder)->merge([
        new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0),
    ], graphProfile());

    expect($graph)->toContain('[0:a:0]aresample=48000:async=1,aformat=sample_rates=48000:channel_layouts=stereo[a0]')
        ->and($graph)->not->toContain('afftdn')
        ->and($graph)->not->toContain('highpass');
});

it('drops the rumble before denoising when room tone is configured', function (): void {
    $graph = (new FilterGraphBuilder(denoiseReductionDb: 12.0, denoiseFloorDb: -25.0, denoiseHighpassHz: 80))
        ->merge([new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0)], graphProfile());

    expect($graph)->toContain(
        '[0:a:0]aresample=48000:async=1,highpass=f=80,afftdn=nr=12.00:nf=-25.00,aformat=sample_rates=48000:channel_layouts=stereo[a0]',
    );
});

it('omits the highpass when only a frequency of zero is configured', function (): void {
    $graph = (new FilterGraphBuilder(denoiseReductionDb: 8.0, denoiseFloorDb: -25.0, denoiseHighpassHz: 0))
        ->merge([new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0)], graphProfile());

    expect($graph)->toContain('async=1,afftdn=nr=8.00:nf=-25.00,aformat=')
        ->and($graph)->not->toContain('highpass');
});
