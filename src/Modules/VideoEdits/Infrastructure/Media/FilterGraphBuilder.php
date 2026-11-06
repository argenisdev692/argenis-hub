<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Infrastructure\Media;

use Modules\VideoEdits\Domain\Services\LoudnormStatsParser;
use Modules\VideoEdits\Domain\ValueObjects\CompressedRange;
use Modules\VideoEdits\Domain\ValueObjects\LoudnessMeasurement;
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
     * Length of the audio fade on each side of a join.
     *
     * This is an **anti-click** ramp, not a breath. The breath is
     * {@see self::DEFAULT_JOIN_AIR_MS}, and the two have different jobs: the air
     * provides the natural pause, while this only stops the waveform from
     * stepping to or from digital zero.
     *
     * It used to be 40 ms and sized by the silence measured at the seam. That is
     * audible on speech: `afade=t=in` multiplies the first 40 ms of the incoming
     * fragment by a rising gain, so the first syllable enters under level — the
     * content review's "empieza bajo y a las décimas recupera el nivel". No curve
     * avoids it; a fade long enough to hear is a fade long enough to duck the
     * word.
     *
     * 5 ms is long enough to remove the discontinuity at 48 kHz and far below
     * the ~20 ms where a gain ramp starts to be perceived as a change of level.
     */
    public const int DEFAULT_JOIN_FADE_MS = 5;

    /**
     * Hard ceiling for {@see self::$joinFadeMs}. Anything longer is the fault
     * this constant exists to prevent, so a stale `.env` cannot bring it back.
     */
    public const int MAX_JOIN_FADE_MS = 10;

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
     *
     * `w` is **milliseconds** in FFmpeg (range 10–100), not seconds. The graph
     * used to pass `0.020`, which FFmpeg rejects outright, so the option never
     * did what its name said. The threshold is 10 rather than FFmpeg's 2: at 2
     * the detector classifies speech transients as clicks and replaces them with
     * autoregressive interpolation, which is heard as a short burst of static at
     * the attack of a word.
     */
    public const int DEFAULT_DECLICK_WINDOW_MS = 20;

    public const int DEFAULT_DECLICK_OVERLAP_PERCENT = 75;

    public const int DEFAULT_DECLICK_THRESHOLD = 10;

    public const string DEFAULT_DECLICK_METHOD = 'save';

    /**
     * Optional repair for a seam that lands on a waveform, never a level tool.
     *
     * The seam itself is already handled by the 5 ms micro-fade in
     * {@see self::joinFades()}; that is deterministic and cannot synthesise
     * anything. `adeclick` is a different kind of operation: it classifies
     * individual samples as impulsive and **replaces** them with an
     * autoregressive interpolation. At its default threshold of 2 it classifies
     * plosives and word attacks too, and the reconstruction is what is heard as
     * a short static/chisporroteo at the start of a phrase.
     *
     * So it ships off. Where a recording has genuine impulse noise (a chair
     * knock, a cable pop), enable it with the higher threshold below and listen
     * to a word onset before shipping; it is a repair, not a default.
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
            // `w` is milliseconds: 10–100. `seconds()` here would emit 0.020
            // and FFmpeg would abort the whole render.
            'adeclick=w=%d:o=%d:t=%d:m=%s',
            max(10, min(100, $this->declickWindowMs)),
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
     *
     * **Linear only.** The measured values come from a first pass over the same
     * cut graph (see {@see self::keepRangesAudio()}), so this pass applies one
     * constant gain for the whole programme. Without a measurement the stage is
     * skipped rather than falling back to dynamic mode: dynamic `loudnorm`
     * decides its gain while it plays, and after a cut or a pause its first
     * phrase enters at the wrong level and climbs back — the exact fault this
     * module was asked to remove. A missing measurement is not worth that.
     */
    private function audioCleanup(?LoudnessMeasurement $measurement = null): string
    {
        $filters = $this->roomTone();

        if ($this->voiceChain !== null) {
            $filters .= $this->voiceChain->filters();
        }

        if ($this->targetLufs === null || $this->truePeakDbTp === null || $measurement === null) {
            return $filters;
        }

        return $filters.sprintf(
            ',loudnorm=I=%s:TP=%s:LRA=%s:measured_I=%s:measured_TP=%s:measured_LRA=%s:measured_thresh=%s:offset=%s:linear=true',
            self::decimal($this->targetLufs),
            self::decimal($this->truePeakDbTp),
            self::linearLoudnessRange($measurement),
            self::decimal($measurement->integratedLufs),
            self::decimal($measurement->truePeakDb),
            self::decimal($measurement->loudnessRange),
            self::decimal($measurement->thresholdLufs),
            self::decimal($measurement->targetOffset),
        );
    }

    /**
     * A target the linear pass can actually honour.
     *
     * `loudnorm` silently reverts to dynamic mode when the requested LRA is
     * lower than the source's. The configured 11 LU is a delivery preference,
     * but linear scaling cannot reduce an existing range — so the target is
     * raised to the measured range instead of letting the filter fall back to
     * the mode this change exists to avoid.
     */
    private function linearLoudnessRange(LoudnessMeasurement $measurement): string
    {
        $configured = $this->loudnessRange ?? 50.0;

        return self::decimal(min(50.0, max($configured, $measurement->loudnessRange + 0.1)));
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
     * `$measurements` is indexed like `$inputProbes`; a missing entry means that
     * input was not measured, and its loudness stage is skipped rather than run
     * in dynamic mode.
     *
     * @param  list<MediaProbe>  $inputProbes
     * @param  array<int, LoudnessMeasurement|null>  $measurements
     */
    #[\NoDiscard]
    public function merge(array $inputProbes, OutputProfile $profile, array $measurements = []): string
    {
        $parts = [];
        $pairs = '';

        foreach ($inputProbes as $index => $probe) {
            $parts[] = sprintf('[%d:v:0]%s[v%d]', $index, $this->videoNormalization($profile), $index);
            $parts[] = $probe->hasAudio
                ? sprintf('[%d:a:0]%s[a%d]', $index, $this->audioNormalization($profile, $measurements[$index] ?? null), $index)
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
     * Every audio join gets a 5 ms anti-click fade and a {@see self::$joinAirMs}
     * hold of room tone, so cuts breathe instead of splicing. The picture holds
     * its last frame for the same air, which is what keeps the two in step — an
     * audio-only gap would drift the lip sync.
     *
     * **The fade is not the breath.** It used to be 40 ms and sized from the
     * measured silence at the seam, which turned the first syllable of every
     * incoming fragment down and let it recover — audible as "the phrase starts
     * low". The air already provides the pause; the fade only removes the step
     * at the waveform level.
     *
     * `$effects` splits the kept ranges further: a compressed range becomes its
     * own segment played faster (`setpts` for the picture, `atempo` for the
     * room tone, so the ambience never drops to digital silence), and each
     * attenuated range is turned down inside its segment with ramps.
     *
     * @param  list<TimeRange>  $keepRanges
     */
    #[\NoDiscard]
    public function keepRanges(
        array $keepRanges,
        MediaProbe $inputProbe,
        OutputProfile $profile,
        ?RenderEffects $effects = null,
        ?LoudnessMeasurement $measurement = null,
    ): string {
        $segments = self::segments($keepRanges, $effects?->compressedRanges ?? []);
        $parts = [];
        $pairs = '';
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => [$range, $speed]) {
            $isSpedUp = $speed > 1.0;
            $outputMs = $isSpedUp ? (int) ceil($range->durationMs() / $speed) : $range->durationMs();
            // The recording's own open and close are not seams; only the ones
            // between two kept fragments need the breath treatment.
            $airMs = $index < $lastIndex ? max(0, $this->joinAirMs) : 0;

            $parts[] = sprintf(
                '[0:v:0]trim=start=%s:end=%s,%s%s[v%d]',
                self::seconds($range->startMs),
                self::seconds($range->endMs),
                $isSpedUp ? sprintf('setpts=(PTS-STARTPTS)/%s', self::factor($speed)) : 'setpts=PTS-STARTPTS',
                self::heldFrame($airMs),
                $index,
            );
            $parts[] = sprintf(
                '%s%s[a%d]',
                $inputProbe->hasAudio ? '[0:a:0]' : '',
                $this->audioSegment($range, $outputMs, $speed, $airMs, $index, $lastIndex, $effects, $inputProbe->hasAudio, $profile),
                $index,
            );
            $pairs .= sprintf('[v%d][a%d]', $index, $index);
        }

        $parts[] = sprintf('%sconcat=n=%d:v=1:a=1[cv][ca]', $pairs, count($segments));
        $parts[] = sprintf('[cv]%s%s', $this->videoNormalization($profile), self::VIDEO_OUTPUT);
        // De-clicking runs on the joined result, before denoise and loudness: a
        // click is a property of a seam, and repairing it before the noise floor
        // is learned keeps the denoiser from treating the repair as signal.
        $parts[] = sprintf(
            '[ca]%s%s',
            implode(',', array_filter([$this->declick(), $this->audioNormalization($profile, $measurement)])),
            self::AUDIO_OUTPUT,
        );

        return implode(';', $parts);
    }

    /**
     * The same cut graph, audio only, for the measurement pass.
     *
     * The second loudness pass has to measure what the render will actually
     * contain: the same trims, the same `atempo` stretches, the same breath
     * attenuations and the same joins. Measuring the source instead would hand
     * `loudnorm` values for material the cut removed.
     *
     * With `$withLoudnessStats` the tail is a measurement filter that prints the
     * five numbers {@see self::audioCleanup()} needs; without it the graph is a
     * faithful audio-only copy of the render chain.
     *
     * @param  list<TimeRange>  $keepRanges
     */
    #[\NoDiscard]
    public function keepRangesAudio(
        array $keepRanges,
        MediaProbe $inputProbe,
        OutputProfile $profile,
        ?RenderEffects $effects = null,
        bool $withLoudnessStats = false,
    ): string {
        $segments = self::segments($keepRanges, $effects?->compressedRanges ?? []);
        $parts = [];
        $pairs = '';
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => [$range, $speed]) {
            $isSpedUp = $speed > 1.0;
            $outputMs = $isSpedUp ? (int) ceil($range->durationMs() / $speed) : $range->durationMs();
            $airMs = $index < $lastIndex ? max(0, $this->joinAirMs) : 0;

            $parts[] = sprintf(
                '%s%s[a%d]',
                $inputProbe->hasAudio ? '[0:a:0]' : '',
                $this->audioSegment($range, $outputMs, $speed, $airMs, $index, $lastIndex, $effects, $inputProbe->hasAudio, $profile),
                $index,
            );
            $pairs .= sprintf('[a%d]', $index);
        }

        $parts[] = sprintf('%sconcat=n=%d:v=0:a=1[ca]', $pairs, count($segments));
        $tail = $withLoudnessStats
            ? $this->loudnessStats($profile)
            : $this->audioNormalization($profile);
        $parts[] = sprintf('[ca]%s%s', implode(',', array_filter([$this->declick(), $tail])), self::AUDIO_OUTPUT);

        return implode(';', $parts);
    }

    /**
     * One segment's audio chain, shared by the render graph and the measurement
     * graph so the two can never describe different material.
     */
    private function audioSegment(
        TimeRange $range,
        int $outputMs,
        float $speed,
        int $airMs,
        int $index,
        int $lastIndex,
        ?RenderEffects $effects,
        bool $hasAudio,
        OutputProfile $profile,
    ): string {
        if (! $hasAudio) {
            return $this->silence($profile, $outputMs);
        }

        $isSpedUp = $speed > 1.0;

        return sprintf(
            'atrim=start=%s:end=%s,asetpts=PTS-STARTPTS%s%s%s%s',
            self::seconds($range->startMs),
            self::seconds($range->endMs),
            $isSpedUp || $effects === null ? '' : self::attenuations($range, $effects),
            $isSpedUp ? sprintf(',atempo=%s', self::factor($speed)) : '',
            self::joinFades($outputMs, fadeIn: $index > 0, fadeOut: $index < $lastIndex),
            self::heldAir($airMs),
        );
    }

    /**
     * The measurement half of the two-pass loudness normalisation.
     *
     * `print_format=json` makes FFmpeg print the five numbers on stderr, mixed
     * with the usual stream dump; {@see LoudnormStatsParser}
     * extracts them. The pass is audio-only, so it costs a decode, never an
     * encode.
     */
    private function loudnessStats(OutputProfile $profile): string
    {
        if ($this->targetLufs === null || $this->truePeakDbTp === null) {
            return sprintf('aresample=%d,anull', $profile->audioSampleRate);
        }

        return sprintf(
            'aresample=%d,loudnorm=I=%s:TP=%s:LRA=%s:print_format=json',
            $profile->audioSampleRate,
            self::decimal($this->targetLufs),
            self::decimal($this->truePeakDbTp),
            self::decimal($this->loudnessRange ?? 50.0),
        );
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
     * so a segment can always be related back to the plan that produced it.
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
     * A short anti-click ramp on the edges that meet another range: the very
     * start and end of the recording are not joins and keep their original
     * attack and decay.
     *
     * The length is fixed and capped at {@see self::MAX_JOIN_FADE_MS}. It is
     * deliberately **not** sized from the seam clearance: a fade that fits inside
     * a measured silence can still be 40 ms long, and 40 ms of rising gain on
     * the first syllable is exactly the level dip this method used to cause.
     * A seam that lands straight on a word now gets the same inaudible 5 ms
     * ramp instead of no ramp at all, so it never clicks and never ducks.
     */
    private function joinFades(int $durationMs, bool $fadeIn, bool $fadeOut): string
    {
        $fadeMs = max(1, min(self::MAX_JOIN_FADE_MS, $this->joinFadeMs));
        $ceiling = max(0, intdiv($durationMs, 2));
        $fadeInMs = $fadeIn ? min($fadeMs, $ceiling) : 0;
        $fadeOutMs = $fadeOut ? min($fadeMs, $ceiling) : 0;

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

    private function audioNormalization(OutputProfile $profile, ?LoudnessMeasurement $measurement = null): string
    {
        return sprintf(
            'aresample=%d:async=1%s,aformat=sample_rates=%d:channel_layouts=%s',
            $profile->audioSampleRate,
            $this->audioCleanup($measurement),
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
