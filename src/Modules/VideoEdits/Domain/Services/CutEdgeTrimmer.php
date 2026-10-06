<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * Swallows the leftovers a splice leaves at its own edges (OBSERVACIONES
 * review round 2).
 *
 * Removing the words around a mistake almost never leaves a clean seam. The
 * speaker was mid-sentence when they stopped, so what sits immediately before
 * the cut is the conjunction they had already committed to — "Vamos a entrar al
 * project de Veralux Group **y**…" — and what sits after it can be half a word,
 * because the transcriber itself heard a word cut off mid-syllable ("**o co-**").
 * Left in place those become exactly the artefacts a reviewer counts as a bad
 * edit: a conjunction hanging in the air, a syllable with nowhere to belong.
 *
 * So a cut grows just far enough to take them with it. Growth is bounded twice
 * over — by `maxAbsorbMs` overall, and by the rule that only a word which cannot
 * legally end a sentence is ever taken — so a cut never grows into a neighbouring
 * clause. Nothing here invents a boundary: it only widens one the caller already
 * proposed, using timings Whisper measured rather than the model's guess.
 *
 * Only words that cannot end a sentence qualify, which is what keeps this safe:
 * "y" and "o" qualify because no sentence in the language ends on them, while
 * "día" does not and is therefore never absorbed.
 */
final readonly class CutEdgeTrimmer
{
    /**
     * @param  list<string>  $danglingWords  words that can never end a sentence, already normalized
     */
    public function __construct(
        private int $maxAbsorbMs,
        private array $danglingWords,
    ) {}

    /**
     * @param  list<TranscriptWord>  $words  the whole transcript, sorted by start time
     * @return array{0: int, 1: int} the widened range, unchanged when there is nothing to take
     */
    #[NoDiscard]
    public function absorbDanglingEdges(int $startMs, int $endMs, array $words): array
    {
        $budget = $this->maxAbsorbMs;
        $floor = max(0, $startMs - $budget);

        foreach ($this->before($startMs, $floor, $words) as $word) {
            if ($startMs - $word->startMs > $budget) {
                break;
            }

            $budget -= $startMs - $word->startMs;
            $startMs = $word->startMs;
        }

        $ceiling = $endMs + $this->maxAbsorbMs;

        foreach ($this->after($endMs, $ceiling, $words) as $word) {
            if ($word->endMs - $endMs > $budget) {
                break;
            }

            $budget -= $word->endMs - $endMs;
            $endMs = $word->endMs;
        }

        return [$startMs, $endMs];
    }

    /**
     * The words immediately before the cut, closest first, stopping at the first
     * one that may legally end a sentence.
     *
     * Closest first matters: the caller walks outwards spending its budget, so a
     * far-to-near order would make the range jump forward instead of back.
     *
     * @param  list<TranscriptWord>  $words
     * @return list<TranscriptWord>
     */
    private function before(int $startMs, int $floorMs, array $words): array
    {
        $collected = [];

        for ($index = count($words) - 1; $index >= 0; $index--) {
            $word = $words[$index];

            if ($word->endMs > $startMs) {
                continue;
            }

            if ($word->startMs < $floorMs) {
                break;
            }

            if (! $this->isDangling($word)) {
                break;
            }

            $collected[] = $word;
        }

        return $collected;
    }

    /**
     * The words immediately after the cut, closest first, stopping at the first
     * one that is a whole word.
     *
     * Only a truncated fragment qualifies here, never a function word: the front
     * of the next fragment is where a sentence legitimately begins, so "Para el
     * prompt número uno" must survive intact. What has to go is a half word the
     * transcriber heard clipped.
     *
     * @param  list<TranscriptWord>  $words
     * @return list<TranscriptWord>
     */
    private function after(int $endMs, int $ceilingMs, array $words): array
    {
        $collected = [];

        foreach ($words as $word) {
            if ($word->startMs < $endMs) {
                continue;
            }

            if ($word->startMs > $ceilingMs) {
                break;
            }

            if (! $word->isTruncated()) {
                break;
            }

            $collected[] = $word;
        }

        return $collected;
    }

    /**
     * A word is a seam leftover only if it cannot end a sentence on its own:
     * either the transcriber recorded it cut off mid-word, or it is a function
     * word with nothing to attach to.
     */
    private function isDangling(TranscriptWord $word): bool
    {
        return $word->isTruncated() || $this->isFunctionWord($word);
    }

    private function isFunctionWord(TranscriptWord $word): bool
    {
        $normalized = $word->normalized();

        return $normalized !== ''
            && ! $word->isBracketedSound()
            && in_array($normalized, $this->danglingWords, true);
    }
}
