<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\ValueObjects\CompressedRange;
use Modules\VideoEdits\Domain\ValueObjects\LoudnessMeasurement;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\RenderEffects;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Modules\VideoEdits\Domain\ValueObjects\VoiceChain;
use Modules\VideoEdits\Infrastructure\Media\FilterGraphBuilder;

function graphProfile(): OutputProfile
{
    return new OutputProfile(1920, 1080, 29.97, 48_000, 2);
}

function loudnessMeasurement(float $lra = 3.70): LoudnessMeasurement
{
    return new LoudnessMeasurement(-25.22, -17.99, $lra, -35.69, 6.96);
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
        '[0:v:0]trim=start=0.000:end=10.150,setpts=PTS-STARTPTS,tpad=stop_mode=clone:stop_duration=0.080[v0]',
        '[0:a:0]atrim=start=0.000:end=10.150,asetpts=PTS-STARTPTS,afade=t=out:st=10.145:d=0.005,apad=pad_dur=0.080[a0]',
        '[0:v:0]trim=start=11.850:end=60.000,setpts=PTS-STARTPTS[v1]',
        '[0:a:0]atrim=start=11.850:end=60.000,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.005[a1]',
        '[v0][a0][v1][a1]concat=n=2:v=1:a=1[cv][ca]',
        '[cv]scale=1920:1080:force_original_aspect_ratio=decrease,pad=1920:1080:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=29.970,format=yuv420p[outv]',
        '[ca]aresample=48000:async=1,aformat=sample_rates=48000:channel_layouts=stereo[outa]',
    ]);
});

it('fades both sides of an inner range with a micro-fade and never the outer edges', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(0, 1_000), new TimeRange(2_000, 3_000), new TimeRange(4_000, 5_000)],
        new MediaProbe(5_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
    );

    // 5 ms is the anti-click floor. A 40 ms ramp here is what used to turn the
    // first syllable of every fragment down and let it recover.
    expect($graph)->toContain('atrim=start=0.000:end=1.000,asetpts=PTS-STARTPTS,afade=t=out:st=0.995:d=0.005,apad=pad_dur=0.080[a0]')
        ->and($graph)->toContain('asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.005,afade=t=out:st=0.995:d=0.005,apad=pad_dur=0.080[a1]')
        ->and($graph)->toContain('atrim=start=4.000:end=5.000,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.005[a2]');
});

it('holds the last frame for exactly as long as the audio holds its air', function (): void {
    $graph = (new FilterGraphBuilder(joinFadeMs: 5, joinAirMs: 120))->keepRanges(
        [new TimeRange(0, 5_000), new TimeRange(6_000, 10_000)],
        new MediaProbe(10_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
    );

    // The whole point of the air being audio-only-safe: picture and sound must
    // gain the same 120 ms or the lip sync drifts for the rest of the video.
    expect(explode(';', $graph)[0])->toBe('[0:v:0]trim=start=0.000:end=5.000,setpts=PTS-STARTPTS,tpad=stop_mode=clone:stop_duration=0.120[v0]')
        ->and(explode(';', $graph)[1])->toBe('[0:a:0]atrim=start=0.000:end=5.000,asetpts=PTS-STARTPTS,afade=t=out:st=4.995:d=0.005,apad=pad_dur=0.120[a0]')
        // The last segment is the recording's own close, not a seam: no air.
        ->and(explode(';', $graph)[3])->toBe('[0:a:0]atrim=start=6.000:end=10.000,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.005[a1]');
});

it('ramps the micro-fade out before the air, never onto the next word', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(0, 4_000), new TimeRange(5_000, 9_000)],
        new MediaProbe(9_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
    );

    // `apad` last, so the fade at 3.995 s ends on silence rather than on the
    // first phoneme after the cut.
    expect(explode(';', $graph)[1])->toContain('afade=t=out:st=3.995:d=0.005,apad=pad_dur=0.080');
});

it('keeps the micro-fade when the air is configured to zero', function (): void {
    $graph = (new FilterGraphBuilder(joinFadeMs: 5, joinAirMs: 0))->keepRanges(
        [new TimeRange(0, 4_000), new TimeRange(5_000, 9_000)],
        new MediaProbe(9_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
    );

    expect($graph)->not->toContain('apad')
        ->and($graph)->not->toContain('tpad')
        ->and($graph)->toContain('afade=t=out:st=3.995:d=0.005');
});

it('does not fade a single kept range, which has no join', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(1_000, 4_000)],
        new MediaProbe(5_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
    );

    expect($graph)->not->toContain('afade')
        ->and($graph)->not->toContain('apad')
        ->and($graph)->not->toContain('tpad');
});

it('plays a tool wait faster, picture and room tone together', function (): void {
    $graph = (new FilterGraphBuilder)->keepRanges(
        [new TimeRange(0, 20_000)],
        new MediaProbe(20_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
        new RenderEffects([new CompressedRange(new TimeRange(5_000, 14_000), 3.0)]),
    );

    expect(array_slice(explode(';', $graph), 0, 6))->toBe([
        '[0:v:0]trim=start=0.000:end=5.000,setpts=PTS-STARTPTS,tpad=stop_mode=clone:stop_duration=0.080[v0]',
        '[0:a:0]atrim=start=0.000:end=5.000,asetpts=PTS-STARTPTS,afade=t=out:st=4.995:d=0.005,apad=pad_dur=0.080[a0]',
        '[0:v:0]trim=start=5.000:end=14.000,setpts=(PTS-STARTPTS)/3.000,tpad=stop_mode=clone:stop_duration=0.080[v1]',
        '[0:a:0]atrim=start=5.000:end=14.000,asetpts=PTS-STARTPTS,atempo=3.000,afade=t=in:st=0:d=0.005,afade=t=out:st=2.995:d=0.005,apad=pad_dur=0.080[a1]',
        '[0:v:0]trim=start=14.000:end=20.000,setpts=PTS-STARTPTS[v2]',
        '[0:a:0]atrim=start=14.000:end=20.000,asetpts=PTS-STARTPTS,afade=t=in:st=0:d=0.005[a2]',
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
        "[0:a:0]atrim=start=12.000:end=20.000,asetpts=PTS-STARTPTS,volume=volume='1-0.9000*clip(min((t-1.000)/0.060,(1.500-t)/0.060),0,1)':eval=frame,afade=t=in:st=0:d=0.005[a1]",
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

it('normalizes loudness with a constant gain from the measurement', function (): void {
    // The measured values make this a linear pass: one gain for the whole
    // programme, no gain ramp after a pause.
    $graph = (new FilterGraphBuilder(targetLufs: -16.0, truePeakDbTp: -1.5, loudnessRange: 11.0))
        ->merge([new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0)], graphProfile(), [loudnessMeasurement()]);

    expect($graph)->toContain(
        'aresample=48000:async=1,loudnorm=I=-16.00:TP=-1.50:LRA=11.00:measured_I=-25.22:measured_TP=-17.99:measured_LRA=3.70:measured_thresh=-35.69:offset=6.96:linear=true,aformat=',
    );
});

it('never falls back to dynamic loudness when a file could not be measured', function (): void {
    // Dynamic mode is the fault, not the fallback. Unmeasurable audio is left
    // at its own level rather than pumped back to the target.
    $graph = (new FilterGraphBuilder(targetLufs: -16.0, truePeakDbTp: -1.5, loudnessRange: 11.0))
        ->merge([new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0)], graphProfile());

    expect($graph)->not->toContain('loudnorm')
        ->and($graph)->not->toContain('linear=true');
});

it('raises the LRA target to the measured range so the pass stays linear', function (): void {
    // `loudnorm` silently reverts to dynamic mode when the target LRA is below
    // the source's; 20.10 keeps the constant-gain path.
    $graph = (new FilterGraphBuilder(targetLufs: -16.0, truePeakDbTp: -1.5, loudnessRange: 11.0))
        ->merge(
            [new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0)],
            graphProfile(),
            [loudnessMeasurement(lra: 20.0)],
        );

    expect($graph)->toContain('LRA=20.10')
        ->and($graph)->toContain('linear=true');
});

it('denoises before normalizing, never the other way round', function (): void {
    // Normalizing first would hand `afftdn` a raised noise floor to learn,
    // which subtracts more of the voice than of the hiss.
    $graph = (new FilterGraphBuilder(
        denoiseReductionDb: 12.0,
        denoiseFloorDb: -25.0,
        denoiseHighpassHz: 80,
        targetLufs: -16.0,
        truePeakDbTp: -1.5,
        loudnessRange: 11.0,
    ))->merge(
        [new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0)],
        graphProfile(),
        [loudnessMeasurement()],
    );

    expect($graph)->toContain(
        'async=1,highpass=f=80,afftdn=nr=12.00:nf=-25.00,loudnorm=I=-16.00:TP=-1.50:LRA=11.00:measured_I=',
    );
});

it('normalizes only when the target, the peak and a measurement are known', function (): void {
    $graph = (new FilterGraphBuilder(targetLufs: -16.0))
        ->merge([new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0)], graphProfile(), [loudnessMeasurement()]);

    expect($graph)->not->toContain('loudnorm');
});

it('leaves the audio chain alone for an OBS recording, which is already processed', function (): void {
    // No voice chain and no denoiser configured: the RNNoise, compressor,
    // expander and limiter from guia_audio_obs.md are already baked into the
    // file, and repeating any of them is what makes audio sound worse.
    $graph = (new FilterGraphBuilder(targetLufs: -16.0, truePeakDbTp: -1.5, loudnessRange: 11.0))
        ->merge(
            [new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0)],
            graphProfile(),
            [loudnessMeasurement()],
        );

    expect($graph)->toContain('aresample=48000:async=1,loudnorm=')
        ->and($graph)->not->toContain('arnndn')
        ->and($graph)->not->toContain('acompressor')
        ->and($graph)->not->toContain('agate')
        ->and($graph)->not->toContain('alimiter')
        ->and($graph)->not->toContain('afftdn');
});

it('puts the voice chain between the denoiser and the loudness target', function (): void {
    $graph = (new FilterGraphBuilder(
        denoiseReductionDb: 12.0,
        denoiseFloorDb: -25.0,
        denoiseHighpassHz: 80,
        targetLufs: -16.0,
        truePeakDbTp: -1.5,
        loudnessRange: 11.0,
        voiceChain: new VoiceChain(
            compressor: ['threshold_db' => -18.0, 'ratio' => 3.0, 'attack_ms' => 6.0, 'release_ms' => 100.0, 'makeup_db' => 3.0, 'knee' => 2.82843],
            limiter: ['ceiling_db' => -3.0, 'attack_ms' => 5.0, 'release_ms' => 60.0],
        ),
    ))->merge(
        [new MediaProbe(30_000, 'mov,mp4', true, true, 1280, 720, 30.0)],
        graphProfile(),
        [loudnessMeasurement()],
    );

    expect($graph)->toContain('async=1,highpass=f=80,afftdn=nr=12.00:nf=-25.00,acompressor=')
        ->and($graph)->toContain('alimiter=limit=0.70795:attack=5.00000:release=60.00000:level=false,loudnorm=I=-16.00')
        // Loudness is last so the limiter cannot win the level fight: it acts on
        // the raised signal, and its ceiling then bounds what loudnorm produced.
        ->and(strpos($graph, 'loudnorm'))->toBeGreaterThan(strpos($graph, 'alimiter'));
});

function seamGraph(RenderEffects $effects, bool $declick = false): string
{
    return (new FilterGraphBuilder(declickEnabled: $declick))->keepRanges(
        [new TimeRange(0, 5_000), new TimeRange(6_000, 20_000)],
        new MediaProbe(20_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
        $effects,
    );
}

it('uses the same inaudible micro-fade whatever silence the seam has', function (): void {
    // Clearance used to size the fade, so a seam with 5 s of air got a 40 ms
    // ramp on the first syllable. The fade is now fixed; the air is the breath.
    $graph = seamGraph(new RenderEffects(seamClearances: [[5_000, 1_000], [1_000, 14_000]]));

    expect(explode(';', $graph)[1])->toContain('afade=t=out:st=4.995:d=0.005')
        ->and(explode(';', $graph)[3])->toContain('afade=t=in:st=0:d=0.005');
});

it('keeps the micro-fade even when the seam lands straight on a word', function (): void {
    // Zero clearance is exactly where a click would land. A 5 ms ramp is
    // inaudible on the attack and still removes the discontinuity; dropping it
    // and leaving the repair to `adeclick` is what produced the static.
    $graph = seamGraph(new RenderEffects(seamClearances: [[5_000, 0], [0, 14_000]]), declick: true);

    expect(explode(';', $graph)[3])->toContain('afade=t=in:st=0:d=0.005')
        ->and($graph)->toContain('adeclick=');
});

it('caps the anti-click fade at 10 ms even when an old config asks for more', function (): void {
    // The regression guard: 40 ms was audible as a level dip, so a stale `.env`
    // must not be able to restore it.
    $graph = (new FilterGraphBuilder(joinFadeMs: 40))->keepRanges(
        [new TimeRange(0, 5_000), new TimeRange(6_000, 20_000)],
        new MediaProbe(20_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
        graphProfile(),
    );

    expect($graph)->toContain('afade=t=in:st=0:d=0.010')
        ->and($graph)->not->toContain('d=0.040');
});

it('de-clicks once on the joined result, with the window in milliseconds', function (): void {
    $graph = seamGraph(new RenderEffects, declick: true);

    // `w` is FFmpeg milliseconds (10–100). `0.020` is out of range and aborts
    // the render; `t=2` is so sensitive that it treats word attacks as clicks.
    expect(substr_count($graph, 'adeclick='))->toBe(1)
        ->and($graph)->toContain('[ca]adeclick=w=20:o=75:t=10:m=save,aresample=');
});

it('de-clicks ahead of denoising so the repair is not read as signal', function (): void {
    $graph = (new FilterGraphBuilder(declickEnabled: true, denoiseReductionDb: 12.0, denoiseFloorDb: -25.0))
        ->keepRanges(
            [new TimeRange(0, 5_000), new TimeRange(6_000, 20_000)],
            new MediaProbe(20_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
            graphProfile(),
        );

    expect(strpos($graph, 'adeclick='))->toBeLessThan(strpos($graph, 'afftdn'));
});

it('leaves de-clicking out unless it is configured', function (): void {
    expect(seamGraph(new RenderEffects))->not->toContain('adeclick=');
});

it('builds an audio-only copy of the cut graph for the measurement pass', function (): void {
    $graph = (new FilterGraphBuilder(targetLufs: -16.0, truePeakDbTp: -1.5, loudnessRange: 11.0))
        ->keepRangesAudio(
            [new TimeRange(0, 5_000), new TimeRange(6_000, 20_000)],
            new MediaProbe(20_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
            graphProfile(),
            withLoudnessStats: true,
        );

    expect($graph)->not->toContain('[0:v:0]')
        ->and($graph)->not->toContain('[v0]')
        ->and($graph)->toContain('atrim=start=0.000:end=5.000')
        ->and($graph)->toContain('atrim=start=6.000:end=20.000')
        ->and($graph)->toContain('concat=n=2:v=0:a=1[ca]')
        ->and($graph)->toContain('[ca]aresample=48000,loudnorm=I=-16.00:TP=-1.50:LRA=11.00:print_format=json[outa]');
});

it('uses the measurement in the render graph, not just in the measuring pass', function (): void {
    $graph = (new FilterGraphBuilder(targetLufs: -16.0, truePeakDbTp: -1.5, loudnessRange: 11.0))
        ->keepRanges(
            [new TimeRange(0, 5_000), new TimeRange(6_000, 20_000)],
            new MediaProbe(20_000, 'mov,mp4', true, true, 1920, 1080, 30.0),
            graphProfile(),
            measurement: loudnessMeasurement(),
        );

    expect($graph)->toContain(
        'loudnorm=I=-16.00:TP=-1.50:LRA=11.00:measured_I=-25.22:measured_TP=-17.99:measured_LRA=3.70:measured_thresh=-35.69:offset=6.96:linear=true,aformat=',
    );
});
