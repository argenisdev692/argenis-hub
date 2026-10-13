<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * Consumes the silence a cut leaves behind, then restores a fixed breath.
 *
 * A cut proposed from word timings spans exactly the removed words — the AI
 * resolves a word-index range to `[firstWord.startMs, lastWord.endMs]` and
 * nothing more. The silence that surrounded the removed phrase survives on BOTH
 * sides of that range, so joining the two kept fragments welds silence to
 * silence. The result is the hole this class exists to close: "vamos… a ….
 * ingresar este prompt", where the listener hears the edit's own bookkeeping
 * rather than a cut.
 *
 * Silence cuts never hit this, because {@see CutPlanner} already insets them by
 * a padding inside the silence. Every other reason — filler, stutter,
 * repetition, and all four the AI proposes — removes WORDS and leaves the air
 * around them untouched, so the air is what has to be handled here.
 *
 * **Consistency is the point, not zero.** auto-editor's `--margin` (0.2 s each
 * side by default) works the same way: consume the flanking silence, then put a
 * fixed amount back. A uniform breath reads as an intentional cut; whatever
 * silence happened to be lying there reads as a glitch. So the target is a
 * constant `breathMs` of air at every seam, never the minimum.
 *
 * The breath is the TOTAL air left at the seam, split across the two sides in
 * proportion to the silence each side actually had, so a seam with one long
 * pause and one short one does not come out lopsided.
 *
 * **Growth is bounded twice**, and the second bound is the one that matters:
 * the total taken is capped by `absorbMs`, and no expansion can ever reach a
 * neighbouring word, because each share is a fraction of the silence that side
 * actually had. A margin meant to tidy air can therefore never swallow a word —
 * it does not know or care what the word was, which is what keeps this separate
 * from {@see CutEdgeTrimmer}, whose whole job is taking words.
 */
final readonly class CutSeamTrimmer
{
    /**
     * @param  int  $absorbMs  most silence one seam may consume in total
     * @param  int  $breathMs  air guaranteed at the seam afterwards, across both sides
     */
    public function __construct(
        private int $absorbMs,
        private int $breathMs,
    ) {}

    /**
     * @param  list<TranscriptWord>  $words  the whole transcript, sorted by start time
     * @return array{0: int, 1: int} the widened range, unchanged when there is no silence to take
     */
    #[NoDiscard]
    public function absorbFlankingSilence(int $startMs, int $endMs, array $words): array
    {
        if ($this->absorbMs <= 0 || $this->breathMs <= 0 || $words === []) {
            return [$startMs, $endMs];
        }

        $previous = $this->neighbourBefore($startMs, $words);
        $next = $this->neighbourAfter($endMs, $words);

        // Without a neighbour on a side there is nothing to measure against, so
        // that side contributes no silence and takes no share. A cut at the very
        // start or end of a recording has no seam to smooth on that side anyway.
        $leftMs = $previous instanceof TranscriptWord ? max(0, $startMs - $previous->endMs) : 0;
        $rightMs = $next instanceof TranscriptWord ? max(0, $next->startMs - $endMs) : 0;
        $availableMs = $leftMs + $rightMs;

        // The seam is already inside the breath, or there is no silence on either
        // side to redistribute. Expanding here could only reach into a word.
        if ($availableMs <= $this->breathMs) {
            return [$startMs, $endMs];
        }

        $removableMs = min($availableMs - $this->breathMs, $this->absorbMs);
        $leftShare = (int) round($removableMs * ($leftMs / $availableMs));

        return [
            max(0, $startMs - $leftShare),
            // The remainder absorbs the rounding, so the two shares always add
            // back to exactly what was authorised.
            $endMs + ($removableMs - $leftShare),
        ];
    }

    /**
     * The last word that ends at or before the cut, which is the one whose tail
     * the seam must never reach into.
     */
    private function neighbourBefore(int $startMs, array $words): ?TranscriptWord
    {
        $found = null;

        foreach ($words as $word) {
            if ($word->endMs > $startMs) {
                break;
            }

            $found = $word;
        }

        return $found;
    }

    /**
     * The first word that starts at or after the cut.
     */
    private function neighbourAfter(int $endMs, array $words): ?TranscriptWord
    {
        foreach ($words as $word) {
            if ($word->startMs >= $endMs) {
                return $word;
            }
        }

        return null;
    }
}
