<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Media;

use Modules\VideoEdits\Domain\ValueObjects\CompressedRange;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Domain\ValueObjects\OutputProfile;
use Modules\VideoEdits\Domain\ValueObjects\RenderEffects;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Modules\VideoEdits\Domain\ValueObjects\VoiceChain;

/**
 * Builds FFmpeg filtergraphs from validated numbers only (OWASP §3 · AD-4, AD-5).
 *
 * Both graphs end in the labels `[outv]` / `[outa]`, normalized to the output
 * profile. Numbers are formatted with `%F` so the locale can never turn a
 * decimal point into a comma.
 */
final readonly class FilterGraphBuilder
{
    public const string VIDEO_OUTPUT = '[outv]';

    public const string AUDIO_OUTPUT = '[outa]';

    /**
     * Length of the audio fade on each side of a join. A hard cut between two
     * waveforms that are not at zero clicks; 15 ms is too short to hear as a
     * fade but long enough to remove the click.
     *
     * The default is deliberately larger than that anti-click floor. The
     * round-2 review called the seam at 05:22 a "salto de audio/respiración muy
     * brusco", which is not a click problem but a transition problem — the splice
     * is audible as a splice. {@see self::DEFAULT_JOIN_AIR_MS} is what the
     * reviewer asked for instead of a crossfade, and the two only work together:
     * the fade ramps into the air so the level never steps, and the air is long
     * enough to read as the breath the speaker took there.
     */
    public const int DEFAULT_JOIN_FADE_MS = 40;

    /**
     * Room tone held after the fade-out and before the next fade-in.
     *
     * This is the reviewer's second option, and it is the only one a
     * `trim`+`concat` graph can honour. A real crossfade overlaps the two
     * clips, so the output is that much shorter than the picture and the lip sync
     * drifts for the rest of the video; on speech the overlap also smears two
     * syllables into one phaser artifact. Holding the last frame for exactly as
     * long as the audio holds the air keeps picture and sound locked.
     */
    public const int DEFAULT_JOIN_AIR_MS = 80;

    /**
     * FFmpeg `adeclick` defaults, restated here so config can read them and a
     * test can assert the chain without depending on the build's own defaults.
     */
    public const int DEFAULT_DECLICK_WINDOW_MS = 20;

    public const int DEFAULT_DECLICK_OVERLAP_PERCENT = 75;

    public const int DEFAULT_DECLICK_THRESHOLD = 2;

    public const string DEFAULT_DECLICK_METHOD = 'save';

    /**
     * Why this exists instead of a longer fade.
     *
     * An `afade` is a gain ramp: it multiplies whatever is in its window, and it
     * does not care whether that is room tone or a syllable. Applied where a seam
     * lands on speech it ramps the word's attack down from zero, and the word is
     * audibly quieter than its neighbours — the fault reported as "some words lose
     * volume when I tidy them". No curve fixes it: `tri`, `qsin` and `exp` all
     * change the SHAPE of the dip, never whether it happens.
     *
     * `adeclick` is the filter built for this job and it is a different kind of
     * operation. It classifies individual samples as impulsive, reconstructs
     * those from their neighbours by autoregressive interpolation, and with
     * `method=save` (overlap-save) leaves every sample it did not classify
     * bit-identical. So the click is repaired and the word is not touched —
     * which is the outcome a fade was only ever approximating.
     *
     * It runs ONCE, on the concatenated result, never per segment: a click is a
     * property of a seam, and a per-segment pass would also be looking at speech
     * that was never discontinuous.
     */
    private function declick(): string
    {
        if (! $this->declickEnabled) {
            return '';
        }

        return sprintf(
            'adeclick=w=%s:o=%d:t=%d:m=%s',
            self::seconds($this->declickWindowMs),
            max(50, min(95, $this->declickOverlapPercent)),
            max(1, min(100, $this->declickThreshold)),
            $this->declickMethod === 'add' ? 'add' : 'save',
        );
    }

    /**
     * Room-tone cleanup, off unless every value is configured.
     *
     * `afftdn` is a spectral denoiser: it learns the noise floor of the material
     * and subtracts it, which is what a bad room leaves behind — a steady hiss
     * and the decaying tail of every word bleeding into the next one. It is NOT
     * true de-reverb: a long reverb tail is a much larger structure than the
     * noise floor, so expect this to take the edge off rather than remove it.
     * A recording with heavy reverberation needs fixing at the desk.
     *
     * Leaving any of the three null keeps the audio chain byte-identical to what
     * it was before this rule existed, which is why the module ships with the
     * whole feature dark.
     */
    public function __construct(
        private ?float $denoiseReductionDb = null,
        private ?float $denoiseFloorDb = null,
        private ?int $denoiseHighpassHz = null,
        private int $joinFadeMs = self::DEFAULT_JOIN_FADE_MS,
        private int $joinAirMs = self::DEFAULT_JOIN_AIR_MS,
        private ?float $targetLufs = null,
        private ?float $truePeakDbTp = null,
        private ?float $loudnessRange = null,
        private ?VoiceChain $voiceChain = null,
        private bool $declickEnabled = false,
        private int $declickWindowMs = self::DEFAULT_DECLICK_WINDOW_MS,
        private int $declickOverlapPercent = self::DEFAULT_DECLICK_OVERLAP_PERCENT,
        private int $declickThreshold = self::DEFAULT_DECLICK_THRESHOLD,
        private string $declickMethod = self::DEFAULT_DECLICK_METHOD,
    ) {}

    /**
     * The capture chain, then loudness normalisation, or nothing at all when
     * they are not configured.
     *
     * The highpass comes first on purpose: rumble below the speech band is what
     * a desk knocked against a wall contributes, and dropping it before the
     * denoiser stops the denoiser from spending its budget on it.
     *
     * The dynamics follow OBS's own order — suppress, compress, expand, limit —
     * because that order is what the guide's measurements were taken with, and
     * swapping any two of them changes the result.
     *
     * `loudnorm` goes last on purpose too. Denoising subtracts energy from the
     * whole signal, so the words that were already quiet come out quieter; a low
     * microphone threshold is therefore made worse by the stage that precedes
     * this one, and only normalisation brings them back. Normalising first would
     * instead hand `afftdn` a raised noise floor to learn, which is worse.
     */
    private function audioCleanup(): string
    {
        $filters = $this->roomTone();

        if ($this->voiceChain !== null) {
            $filters .= $this->voiceChain->filters();
        }

        if ($this->targetLufs === null || $this->truePeakDbTp === null) {
            return $filters;
        }

        return $filters.sprintf(
            ',loudnorm=I=%s:TP=%s%s',
            self::decimal($this->targetLufs),
            self::decimal($this->truePeakDbTp),
            $this->loudnessRange === null ? '' : ':LRA='.self::decimal($this->loudnessRange),
        );
    }

    private function roomTone(): string
    {
        if ($this->denoiseReductionDb === null || $this->denoiseFloorDb === null) {
            return '';
        }

        $filters = '';

        if ($this->denoiseHighpassHz !== null && $this->denoiseHighpassHz > 0) {
            $filters .= sprintf(',highpass=f=%s', $this->denoiseHighpassHz);
        }

        return $filters.sprintf(
            ',afftdn=nr=%s:nf=%s',
            self::decimal($this->denoiseReductionDb),
            self::decimal($this->denoiseFloorDb),
        );
    }

    /**
     * Normalize every input to the profile (scale + pad, never stretch) and join
     * them in order. Inputs without audio get generated silence of their length.
     *
     * @param  list<MediaProbe>  $inputProbes
     */
    #[\NoDiscard]
    public function merge(array $inputProbes, OutputProfile $profile): string
    {
        $parts = [];
        $pairs = '';

        foreach ($inputProbes as $index => $probe) {
            $parts[] = sprintf('[%d:v:0]%s[v%d]', $index, $this->videoNormalization($profile), $index);
            $parts[] = $probe->hasAudio
                ? sprintf('[%d:a:0]%s[a%d]', $index, $this->audioNormalization($profile), $index)
                : sprintf('%s[a%d]', $this->silence($profile, $probe->durationMs), $index);
            $pairs .= sprintf('[v%d][a%d]', $index, $index);
        }

        $parts[] = sprintf('%sconcat=n=%d:v=1:a=1%s%s', $pairs, count($inputProbes), self::VIDEO_OUTPUT, self::AUDIO_OUTPUT);

        return implode(';', $parts);
    }

    /**
     * Ramp in and out of a turned-down breath. Long enough that the level
     * change is inaudible as a step even though `volume` re-evaluates only once
     * per audio frame (~21 ms at 48 kHz).
     */
    public const int ATTENUATION_RAMP_MS = 60;

    /**
     * Keep only the given ranges of a single input, frame- and sample-accurately
     * (trim/atrim + reset timestamps + concat), then normalize to the profile.
     *
     * Every audio join gets a {@see self::$joinFadeMs} fade and a
     * {@see self::$joinAirMs} hold of room tone, so cuts breathe instead of
     * splicing. The picture holds its last frame for the same air, which is what
     * keeps the two in step — an audio-only gap would drift the lip sync.
     *
     * `$effects` splits the kept ranges further: a compressed range becomes its
     * own segment played faster (`setpts` for the picture, `atempo` for the
     * room tone, so the ambience never drops to digital silence), and each
     * attenuated range is turned down inside its segment with ramps.
     *
     * @param  list<TimeRange>  $keepRanges
     */
    #[\NoDiscard]
    public function keepRanges(array $keepRanges, MediaProbe $inputProbe, OutputProfile $profile, ?RenderEffects $effects = null): string
    {
        $segments = self::segments($keepRanges, $effects?->compressedRanges ?? []);
        $parts = [];
        $pairs = '';
        $lastIndex = count($segments) - 1;
        $clearances = $effects?->seamClearances ?? [];

        // A compressed wait splits one kept range into several segments, so the
        // clearances — which are measured per keep range — have to be re-anchored
        // to segments before they can be used.
        $firstOfKeep = [];
        $lastOfKeep = [];

        foreach ($segments as $index => [, , $keepIndex]) {
            $firstOfKeep[$keepIndex] ??= $index;
            $lastOfKeep[$keepIndex] = $index;
        }

        foreach ($segments as $index => [$range, $speed, $keepIndex]) {
            $isSpedUp = $speed > 1.0;
            $outputMs = $isSpedUp ? (int) ceil($range->durationMs() / $speed) : $range->durationMs();
            // The recording's own open and close are not seams; only the ones
            // between two kept fragments need the breath treatment.
            $airMs = $index < $lastIndex ? max(0, $this->joinAirMs) : 0;
            // A sped-up segment plays its source time faster, so air measured on
            // the source timeline has to be scaled onto the output it lands in.
            $leadScale = $isSpedUp ? 1.0 / $speed : 1.0;
            $clearance = $clearances[$keepIndex] ?? null;

            $parts[] = sprintf(
                '[0:v:0]trim=start=%s:end=%s,%s%s[v%d]',
                self::seconds($range->startMs),
                self::seconds($range->endMs),
                $isSpedUp ? sprintf('setpts=(PTS-STARTPTS)/%s', self::factor($speed)) : 'setpts=PTS-STARTPTS',
                self::heldFrame($airMs),
                $index,
            );
            $parts[] = $inputProbe->hasAudio
                ? sprintf(
                    '[0:a:0]atrim=start=%s:end=%s,asetpts=PTS-STARTPTS%s%s%s%s[a%d]',
                    self::seconds($range->startMs),
                    self::seconds($range->endMs),
                    $isSpedUp || $effects === null ? '' : self::attenuations($range, $effects),
                    $isSpedUp ? sprintf(',atempo=%s', self::factor($speed)) : '',
                    self::joinFades(
                        $outputMs,
                        fadeIn: $index > 0,
                        fadeOut: $index < $lastIndex,
                        // Only the fragment's own outer edges are seams against
                        // speech; the joins inside a sped-up wait are between two
                        // stretches of tool silence.
                        leadInMs: $clearance !== null && $index === $firstOfKeep[$keepIndex]
                            ? (int) round($clearance[0] * $leadScale)
                            : null,
                        leadOutMs: $clearance !== null && $index === $lastOfKeep[$keepIndex]
                            ? (int) round($clearance[1] * $leadScale)
                            : null,
                    ),
                    self::heldAir($airMs),
                    $index,
                )
                : sprintf('%s[a%d]', $this->silence($profile, $outputMs), $index);
            $pairs .= sprintf('[v%d][a%d]', $index, $index);
        }

        $parts[] = sprintf('%sconcat=n=%d:v=1:a=1[cv][ca]', $pairs, count($segments));
        $parts[] = sprintf('[cv]%s%s', $this->videoNormalization($profile), self::VIDEO_OUTPUT);
        // De-clicking runs on the joined result, before denoise and loudness: a
        // click is a property of a seam, and repairing it before the noise floor
        // is learned keeps the denoiser from treating the repair as signal.
        $parts[] = sprintf(
            '[ca]%s%s',
            implode(',', array_filter([$this->declick(), $this->audioNormalization($profile)])),
            self::AUDIO_OUTPUT,
        );

        return implode(';', $parts);
    }

    /**
     * Holds the segment's last frame for the air, so the seam shows a still
     * picture under the held breath instead of jumping to the next cut's frame
     * one breath early.
     */
    private static function heldFrame(int $airMs): string
    {
        return $airMs <= 0
            ? ''
            : sprintf(',tpad=stop_mode=clone:stop_duration=%s', self::seconds($airMs));
    }

    /**
     * The silence that carries that air. `apad` comes AFTER the fade-out so the
     * fade ramps into the gap rather than into the next word — which is the
     * whole point, since a fade that ends on speech is exactly the abruptness
     * this replaces.
     */
    private static function heldAir(int $airMs): string
    {
        return $airMs <= 0
            ? ''
            : sprintf(',apad=pad_dur=%s', self::seconds($airMs));
    }

    /**
     * Each kept range, split where a compressed range starts and ends.
     *
     * The third element is the index of the keep range the segment came from,
     * which is what re-anchors the plan's per-fragment seam clearances onto the
     * segments the filter graph actually builds.
     *
     * @param  list<TimeRange>  $keepRanges
     * @param  list<CompressedRange>  $compressedRanges
     * @return list<array{0: TimeRange, 1: float, 2: int}>
     */
    private static function segments(array $keepRanges, array $compressedRanges): array
    {
        $segments = [];

        foreach ($keepRanges as $keepIndex => $keep) {
            $cursor = $keep->startMs;

            foreach ($compressedRanges as $compressed) {
                $start = max($compressed->range->startMs, $keep->startMs);
                $end = min($compressed->range->endMs, $keep->endMs);

                if ($end <= $start) {
                    continue;
                }

                if ($start > $cursor) {
                    $segments[] = [new TimeRange($cursor, $start), 1.0, $keepIndex];
                }

                $segments[] = [new TimeRange($start, $end), $compressed->speed, $keepIndex];
                $cursor = $end;
            }

            if ($keep->endMs > $cursor) {
                $segments[] = [new TimeRange($cursor, $keep->endMs), 1.0, $keepIndex];
            }
        }

        return $segments;
    }

    /**
     * One `volume` filter per breath inside this segment, in segment-local
     * time (the segment's timestamps start at 0 after `asetpts`). The gain
     * ramps linearly from 1 down to the attenuation and back, over
     * {@see self::ATTENUATION_RAMP_MS} or half the breath when it is shorter.
     */
    private static function attenuations(TimeRange $segment, RenderEffects $effects): string
    {
        $floor = 10 ** ($effects->attenuationDb / 20);
        $filters = '';

        foreach ($effects->attenuatedRanges as $breath) {
            $startMs = max($breath->startMs, $segment->startMs) - $segment->startMs;
            $endMs = min($breath->endMs, $segment->endMs) - $segment->startMs;
            $rampMs = min(self::ATTENUATION_RAMP_MS, intdiv($endMs - $startMs, 2));

            if ($rampMs <= 0) {
                continue;
            }

            $filters .= sprintf(
                ",volume=volume='1-%s*clip(min((t-%s)/%s,(%s-t)/%s),0,1)':eval=frame",
                sprintf('%.4F', 1 - $floor),
                self::seconds($startMs),
                self::seconds($rampMs),
                self::seconds($endMs),
                self::seconds($rampMs),
            );
        }

        return $filters;
    }

    /**
     * Fades only the edges that meet another range: the very start and end of
     * the recording are not joins and keep their original attack and decay.
     *
     * `leadInMs` / `leadOutMs` are the silence actually measured at that edge.
     * A null means nothing is known about it — no transcript, so no measurement —
     * and the configured fade is used rather than none, because an unfaded seam
     * clicks. A known zero is different and is honoured: that is a seam landing
     * straight on a word, and fading it would turn the word down, so the fade is
     * dropped and `adeclick` repairs the discontinuity instead.
     */
    private function joinFades(int $durationMs, bool $fadeIn, bool $fadeOut, ?int $leadInMs = null, ?int $leadOutMs = null): string
    {
        $ceiling = max(0, intdiv($durationMs, 2));
        $fadeInMs = $fadeIn ? min($this->joinFadeMs, $ceiling, max(0, $leadInMs ?? $this->joinFadeMs)) : 0;
        $fadeOutMs = $fadeOut ? min($this->joinFadeMs, $ceiling, max(0, $leadOutMs ?? $this->joinFadeMs)) : 0;

        return ($fadeInMs > 0 ? sprintf(',afade=t=in:st=0:d=%s', self::seconds($fadeInMs)) : '')
            .($fadeOutMs > 0 ? sprintf(',afade=t=out:st=%s:d=%s', self::seconds($durationMs - $fadeOutMs), self::seconds($fadeOutMs)) : '');
    }

    private static function factor(float $speed): string
    {
        return sprintf('%.3F', $speed);
    }

    private function videoNormalization(OutputProfile $profile): string
    {
        return sprintf(
            'scale=%1$d:%2$d:force_original_aspect_ratio=decrease,pad=%1$d:%2$d:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=%3$s,format=yuv420p',
            $profile->width,
            $profile->height,
            sprintf('%.3F', $profile->frameRate),
        );
    }

    private function audioNormalization(OutputProfile $profile): string
    {
        return sprintf(
            'aresample=%d:async=1%s,aformat=sample_rates=%d:channel_layouts=%s',
            $profile->audioSampleRate,
            $this->audioCleanup(),
            $profile->audioSampleRate,
            self::channelLayout($profile),
        );
    }

    private function silence(OutputProfile $profile, int $durationMs): string
    {
        return sprintf(
            'anullsrc=channel_layout=%s:sample_rate=%d,atrim=duration=%s',
            self::channelLayout($profile),
            $profile->audioSampleRate,
            self::seconds($durationMs),
        );
    }

    private static function channelLayout(OutputProfile $profile): string
    {
        return $profile->audioChannels === 1 ? 'mono' : 'stereo';
    }

    private static function seconds(int $milliseconds): string
    {
        return sprintf('%.3F', $milliseconds / 1000);
    }

    private static function decimal(float $value): string
    {
        return sprintf('%.2F', $value);
    }
}
