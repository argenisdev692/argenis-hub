<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\ValueObjects\AiRecommendation;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * Measures the speaking pace of a recording from Whisper's word timings — the
 * "ritmo dinámico, sin minipausas" review criterion — and turns it into a
 * `pacing` recommendation. Pure and deterministic: no model is asked, so the
 * numbers in the report are exact rather than estimated.
 *
 * A mini-pause is a gap between two words that is long enough to be heard as
 * a hesitation but shorter than the silence threshold, so silence removal
 * leaves it in the final video. Pace is only advice: a slow delivery cannot be
 * fixed by cutting, which is why this never becomes a cut decision.
 *
 * It also answers the round-2 complaint that the global number cannot see:
 * "ciertos pasos de los ejemplos se muestran muy rápido y el alumno puede
 * perderse". {@see self::rushedStretches()} is RELATIVE — a recording can
 * average a perfectly acceptable rate and still sprint through the part that
 * matters — and it reports timecodes, because advice with no timestamp is advice
 * nobody can act on.
 */
final readonly class SpeechPaceAnalyzer
{
    public function __construct(
        private int $miniPauseMs,
        private int $minMiniPauses,
        private int $slowWordsPerMinute,
        private int $minSpeakingMs,
        private string $defaultLanguage,
        private float $rushRatio = 1.35,
        private int $rushFloorWordsPerMinute = 190,
        private int $rushMinTotalMs = 4_000,
        private int $rushMinWords = 8,
    ) {}

    /** Timecodes a human can act on before the row stops being a checklist. */
    private const int MAX_REPORTED_RUSHES = 5;

    /**
     * @param  int|null  $silenceThresholdMs  null when silence removal is off, so every pause survives
     */
    #[\NoDiscard]
    public function recommendation(Transcript $transcript, ?int $silenceThresholdMs): ?AiRecommendation
    {
        $words = array_values(array_filter(
            $transcript->words,
            static fn (TranscriptWord $word): bool => ! $word->isBracketedSound(),
        ));

        if (count($words) < 2) {
            return null;
        }

        $speakingMs = array_last($words)->endMs - array_first($words)->startMs;

        if ($speakingMs < $this->minSpeakingMs) {
            return null;
        }

        $wordsPerMinute = (int) round(count($words) / ($speakingMs / 60_000));
        $miniPauses = $this->survivingMiniPauses($words, $silenceThresholdMs);

        if (count($miniPauses) < $this->minMiniPauses && $wordsPerMinute >= $this->slowWordsPerMinute) {
            return null;
        }

        return $this->isSpanish($transcript)
            ? new AiRecommendation(
                kind: AiRecommendationKind::Pacing,
                title: sprintf('Ritmo: %d palabras/min, %d minipausas', $wordsPerMinute, count($miniPauses)),
                detail: sprintf(
                    'Quedan %d pausas de %s s o más entre frases que la eliminación de silencios no quita (suman %s s). %s',
                    count($miniPauses),
                    self::seconds($this->miniPauseMs),
                    self::seconds(array_sum($miniPauses)),
                    $wordsPerMinute < $this->slowWordsPerMinute
                        ? sprintf('El ritmo está por debajo de %d palabras/min: conviene regrabar con un tono más fluido.', $this->slowWordsPerMinute)
                        : sprintf('Baja el umbral de silencio a %s s para eliminarlas.', self::seconds($this->miniPauseMs)),
                ),
            )
            : new AiRecommendation(
                kind: AiRecommendationKind::Pacing,
                title: sprintf('Pace: %d words/min, %d mini-pauses', $wordsPerMinute, count($miniPauses)),
                detail: sprintf(
                    '%d pauses of %s s or more remain between sentences that silence removal does not cut (%s s in total). %s',
                    count($miniPauses),
                    self::seconds($this->miniPauseMs),
                    self::seconds(array_sum($miniPauses)),
                    $wordsPerMinute < $this->slowWordsPerMinute
                        ? sprintf('The pace is below %d words/min: consider re-recording with a more fluid delivery.', $this->slowWordsPerMinute)
                        : sprintf('Lower the silence threshold to %s s to remove them.', self::seconds($this->miniPauseMs)),
                ),
            );
    }

    /**
     * @param  list<TranscriptWord>  $words
     * @return list<int> the length of each mini-pause, in milliseconds
     */
    private function survivingMiniPauses(array $words, ?int $silenceThresholdMs): array
    {
        $pauses = [];

        foreach (array_slice($words, 1) as $index => $word) {
            $gapMs = $word->startMs - $words[$index]->endMs;
            $removedBySilence = $silenceThresholdMs !== null && $gapMs >= $silenceThresholdMs;

            if ($gapMs >= $this->miniPauseMs && ! $removedBySilence) {
                $pauses[] = $gapMs;
            }
        }

        return $pauses;
    }

    /**
     * Stretches spoken markedly faster than this recording's own average, as a
     * `rushed_step` recommendation — or null when the delivery is even throughout.
     *
     * **Why relative, not absolute.** A fixed words-per-minute ceiling flags slow
     * speakers for speaking normally and misses fast ones averaging under it. The
     * speaker's own average for THIS recording is the only fair baseline, and the
     * absolute floor exists only to stop a fast-looking run inside an otherwise
     * very slow recording from being reported on the ratio alone.
     *
     * **Why per word, not a sliding window.** A window estimates a rate from a
     * sample and therefore bleeds: the window straddling the edge of a fast
     * stretch is already fast enough to qualify, so the reported range begins a
     * second or two before the rush does. A recommendation whose timecode points
     * at the wrong seconds is worse than none. Per-word rate has no slack — the
     * run starts where the words actually speed up and ends where they stop.
     *
     * **Why the gap counts.** A word's rate is measured from the end of the
     * previous word to the end of this one, so pauses are inside the number. That
     * is what makes the test strict: a rushed stretch has to beat its own pauses
     * to qualify. It also means the finding survives silence removal — the pauses
     * it was measured against are exactly what the edit removes, so the
     * delivered video is faster still.
     *
     * @return list<array{0: int, 1: int}> [startMs, endMs] of each qualifying run
     */
    #[\NoDiscard]
    public function rushedStretches(Transcript $transcript): array
    {
        $words = array_values(array_filter(
            $transcript->words,
            static fn (TranscriptWord $word): bool => ! $word->isBracketedSound(),
        ));

        if (count($words) < 2) {
            return [];
        }

        $speakingMs = array_last($words)->endMs - array_first($words)->startMs;

        if ($speakingMs < $this->minSpeakingMs) {
            return [];
        }

        $threshold = max(
            count($words) / ($speakingMs / 60_000) * $this->rushRatio,
            (float) $this->rushFloorWordsPerMinute,
        );

        return $this->fastRuns($words, $threshold);
    }

    /**
     * Maximal runs of consecutive words over the threshold, in transcript order.
     *
     * The run's own boundaries are the first fast word's start and the LAST fast
     * word's end, never the slow word that closed it — extending into that word
     * would report a stretch that was never spoken fast.
     *
     * @param  list<TranscriptWord>  $words
     * @return list<array{0: int, 1: int}>
     */
    private function fastRuns(array $words, float $threshold): array
    {
        $runs = [];
        $runStartMs = null;
        $runEndMs = null;
        $runWords = 0;
        $previousEndMs = null;

        foreach ($words as $word) {
            $spanMs = $word->endMs - ($previousEndMs ?? $word->startMs);
            $previousEndMs = $word->endMs;

            // A word with no measurable span cannot carry a rate; dividing by it
            // would invent an arbitrarily fast one. It is also not evidence
            // against the run around it, so the run simply stays open.
            if ($spanMs <= 0) {
                continue;
            }

            if (60_000 / $spanMs < $threshold) {
                if ($runStartMs !== null && $runEndMs !== null
                    && $runEndMs - $runStartMs >= $this->rushMinTotalMs
                    && $runWords >= $this->rushMinWords) {
                    $runs[] = [$runStartMs, $runEndMs];
                }

                $runStartMs = null;
                $runEndMs = null;
                $runWords = 0;

                continue;
            }

            $runStartMs ??= $word->startMs;
            $runEndMs = $word->endMs;
            $runWords++;
        }

        if ($runStartMs !== null && $runEndMs !== null
            && $runEndMs - $runStartMs >= $this->rushMinTotalMs
            && $runWords >= $this->rushMinWords) {
            $runs[] = [$runStartMs, $runEndMs];
        }

        return $runs;
    }

    /**
     * The rushed-stretch finding as a report row, or null when there is none.
     *
     * Separate from {@see self::recommendation()} because the two answer different
     * questions — one is "is the delivery too slow overall", the other "is this
     * specific passage too fast" — and a recording can be one without the other.
     */
    #[\NoDiscard]
    public function rushRecommendation(Transcript $transcript): ?AiRecommendation
    {
        $stretches = $this->rushedStretches($transcript);

        if ($stretches === []) {
            return null;
        }

        // The report is read by a person deciding whether to re-record. Five
        // timecodes is a re-record list; twenty is a wall of text.
        $shown = array_slice($stretches, 0, self::MAX_REPORTED_RUSHES);
        $timecodes = array_map(
            static fn (array $range): string => sprintf('%s–%s', self::timecode($range[0]), self::timecode($range[1])),
            $shown,
        );
        $totalMs = array_sum(array_map(
            static fn (array $range): int => $range[1] - $range[0],
            $stretches,
        ));

        return $this->isSpanish($transcript)
            ? new AiRecommendation(
                kind: AiRecommendationKind::RushedStep,
                title: sprintf(
                    '%d %s atropellado%s (%s s)',
                    count($stretches),
                    count($stretches) === 1 ? 'tramo' : 'tramos',
                    count($stretches) === 1 ? '' : 's',
                    self::seconds($totalMs),
                ),
                detail: sprintf(
                    'Estos tramos van claramente más rápido que el resto de la grabación (%s), y son justo donde el alumno se pierde: %s.%s Para corregirlos hay que regrabar más despacio esos puntos concretos; en el corte no se arregla.',
                    sprintf('%d palabras/min', $this->rushFloorWordsPerMinute),
                    implode(', ', $timecodes),
                    count($stretches) > count($shown) ? sprintf(' (y %d más)', count($stretches) - count($shown)) : '',
                ),
                startMs: $shown[0][0],
                endMs: array_last($stretches)[1],
            )
            : new AiRecommendation(
                kind: AiRecommendationKind::RushedStep,
                title: sprintf(
                    '%d rushed stretch%s (%s s)',
                    count($stretches),
                    count($stretches) === 1 ? '' : 'es',
                    self::seconds($totalMs),
                ),
                detail: sprintf(
                    'These stretches run markedly faster than the rest of the recording (%d words/min), and they are where a viewer gets lost: %s.%s Re-record those exact points more slowly; a cut cannot fix it.',
                    $this->rushFloorWordsPerMinute,
                    implode(', ', $timecodes),
                    count($stretches) > count($shown) ? sprintf(' (and %d more)', count($stretches) - count($shown)) : '',
                ),
                startMs: $shown[0][0],
                endMs: array_last($stretches)[1],
            );
    }

    private function isSpanish(Transcript $transcript): bool
    {
        return str_starts_with(mb_strtolower($transcript->language ?? $this->defaultLanguage), 'es');
    }

    private static function timecode(int $milliseconds): string
    {
        $seconds = intdiv($milliseconds, 1_000);

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private static function seconds(int $milliseconds): string
    {
        return sprintf('%.1F', $milliseconds / 1000);
    }
}
