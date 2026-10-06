<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

use Modules\VideoEdits\Providers\VideoEditsServiceProvider;

/**
 * The FFmpeg half of the capture chain described in `guia_audio_obs.md`,
 * expressed in the same numbers OBS uses.
 *
 * **Why this exists when the source is already processed.** OBS runs the Fifine
 * USB mic through RNNoise, a 3:1 compressor, a 4:1 expander and a limiter, and
 * the guide switches Windows' enhancements off precisely so nothing is applied
 * twice on the way in. So for that recording every stage here is off, and the
 * only thing this module adds is the delivery loudness. Re-running a denoiser
 * over RNNoise output does not clean anything — it takes energy out of speech
 * that is already clean and produces the metallic "underwater" artefact people
 * blame on the recorder. A second compressor on top of the first is the same
 * mistake in a different register.
 *
 * It is here because a recording does not have to come from OBS: a phone, a
 * camera, a screen capture with no mic processing, or someone who followed only
 * half the guide gets raw audio, and for those the same chain is wanted
 * server-side with no second screen to watch.
 *
 * **Pure by design.** This turns numbers into a filter string and touches
 * nothing else — in particular it does not check whether the RNNoise model file
 * exists, because that is I/O and belongs to the composition root
 * ({@see VideoEditsServiceProvider}). Every stage
 * is skipped when it is switched off, so a disabled chain contributes the empty
 * string and the audio chain is byte-identical to what it was before.
 *
 * **dB in, FFmpeg's linear numbers out.** Every one of these filters takes an
 * amplitude ratio, not decibels, so the guide's dB figures are converted here
 * once. Pasting `-18` into `acompressor` would not be a threshold, it would be
 * -18 dBFS of headroom and silence.
 */
final readonly class VoiceChain
{
    /**
     * @param  array{threshold_db: float, ratio: float, attack_ms: float, release_ms: float, makeup_db: float, knee: float}|null  $compressor
     * @param  array{threshold_db: float, ratio: float, attack_ms: float, release_ms: float, range_db: float}|null  $expander
     * @param  array{ceiling_db: float, attack_ms: float, release_ms: float}|null  $limiter
     * @param  string|null  $rnnoiseModel  an existing RNNoise `.rnnn` model, or null to skip
     * @param  float|null  $rnnoiseMix  how much denoised signal to keep, -1 to 1
     */
    public function __construct(
        private ?array $compressor = null,
        private ?array $expander = null,
        private ?array $limiter = null,
        private ?string $rnnoiseModel = null,
        private ?float $rnnoiseMix = null,
    ) {}

    /**
     * The chain in OBS's order: suppress, compress, expand, limit.
     */
    #[\NoDiscard]
    public function filters(): string
    {
        return $this->rnnoise()
            .$this->compressor()
            .$this->expander()
            .$this->limiter();
    }

    public function isEmpty(): bool
    {
        return $this->filters() === '';
    }

    /**
     * `arnndn` with RNNoise's `sh.rnnn`.
     *
     * The path is escaped for **FFmpeg's own filtergraph tokenizer**, which is
     * not a shell: it splits on `:` and treats `\` as its escape character, and
     * `av_get_token` understands single quotes. `escapeshellarg()` is the wrong
     * tool here — it emits cmd/POSIX shell quoting, which FFmpeg misparses, and
     * a Windows drive letter (`C:\models\sh.rnnn`) is exactly the case that
     * breaks, because the colon reads as a filter separator and the path is
     * silently cut at `C`.
     *
     * Verified against FFmpeg 9.0.2: with the escaping below the filter parses
     * and reaches the point of opening the model, failing only because the file
     * is absent — which is the failure the caller is meant to see.
     */
    private function rnnoise(): string
    {
        if ($this->rnnoiseModel === null || trim($this->rnnoiseModel) === '') {
            return '';
        }

        $filters = sprintf(',arnndn=m=%s', self::filterPath($this->rnnoiseModel));

        return $this->rnnoiseMix === null ? $filters : $filters.sprintf(':mix=%s', self::decimal($this->rnnoiseMix));
    }

    /**
     * Backslash-escape the two characters FFmpeg's tokenizer treats specially,
     * then single-quote the whole value so a leading dash cannot read as an
     * option.
     */
    private static function filterPath(string $path): string
    {
        return "'".str_replace(['\\', ':'], ['\\\\', '\\:'], $path)."'";
    }

    /**
     * A 3:1 downward compressor above -18 dB with +3 dB make-up, matching OBS.
     */
    private function compressor(): string
    {
        if ($this->compressor === null) {
            return '';
        }

        return sprintf(
            ',acompressor=threshold=%s:ratio=%s:attack=%s:release=%s:makeup=%s:knee=%s:detection=rms',
            self::amplitude($this->compressor['threshold_db']),
            self::decimal($this->compressor['ratio']),
            self::decimal($this->compressor['attack_ms']),
            self::decimal($this->compressor['release_ms']),
            self::amplitude($this->compressor['makeup_db']),
            self::decimal($this->compressor['knee']),
        );
    }

    /**
     * OBS's Expander, approximated by FFmpeg's `agate`.
     *
     * FFmpeg has no expander: `agate` is a downward gate, so the downward
     * expansion is expressed as a high ratio plus a deep `range`, which is the
     * usual way to get the same audible result. `range` is the maximum
     * attenuation applied below the threshold — the guide's -40 dB puts it
     * exactly as deep as OBS's threshold.
     *
     * A gate this aggressive cuts word onsets when the threshold sits above the
     * quietest syllable, which is the "se cortan los inicios de palabras" row in
     * the guide's own troubleshooting table. That is why the threshold is a
     * config value and why it is set to the guide's -40 dB rather than lower.
     */
    private function expander(): string
    {
        if ($this->expander === null) {
            return '';
        }

        return sprintf(
            ',agate=threshold=%s:ratio=%s:attack=%s:release=%s:range=%s:detection=rms',
            self::amplitude($this->expander['threshold_db']),
            self::decimal($this->expander['ratio']),
            self::decimal($this->expander['attack_ms']),
            self::decimal($this->expander['release_ms']),
            self::amplitude($this->expander['range_db']),
        );
    }

    /**
     * A ceiling at -3 dB, matching OBS's Limiter.
     *
     * `level=false` is not optional. `alimiter` auto-levels to `level_out` by
     * default, which would silently rescale the signal and fight the `loudnorm`
     * that runs after it — the limiter would win, because it is applied to the
     * already-raised signal.
     */
    private function limiter(): string
    {
        if ($this->limiter === null) {
            return '';
        }

        return sprintf(
            ',alimiter=limit=%s:attack=%s:release=%s:level=false',
            self::amplitude($this->limiter['ceiling_db']),
            self::decimal($this->limiter['attack_ms']),
            self::decimal($this->limiter['release_ms']),
        );
    }

    /**
     * Decibels to the linear amplitude every one of these filters wants.
     */
    private static function amplitude(float $decibels): string
    {
        return self::decimal(10 ** ($decibels / 20));
    }

    private static function decimal(float $value): string
    {
        return sprintf('%.5F', $value);
    }
}
