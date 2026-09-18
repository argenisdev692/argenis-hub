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
 */
final readonly class SpeechPaceAnalyzer
{
    public function __construct(
        private int $miniPauseMs,
        private int $minMiniPauses,
        private int $slowWordsPerMinute,
        private int $minSpeakingMs,
        private string $defaultLanguage,
    ) {}

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

    private function isSpanish(Transcript $transcript): bool
    {
        return str_starts_with(mb_strtolower($transcript->language ?? $this->defaultLanguage), 'es');
    }

    private static function seconds(int $milliseconds): string
    {
        return sprintf('%.1F', $milliseconds / 1000);
    }
}
