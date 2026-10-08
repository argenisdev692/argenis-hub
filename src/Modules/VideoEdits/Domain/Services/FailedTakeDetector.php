<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\ValueObjects\FailedTake;
use Modules\VideoEdits\Domain\ValueObjects\FailedTakeAnchor;
use Modules\VideoEdits\Domain\ValueObjects\FailedTakeBoundary;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * Works out where a failed take ended, by walking back from where the speaker
 * restarted.
 *
 * OBSERVACIONES round 2, item 2 — "se han quedado enganches incompletos" — has a
 * cause the marker cut alone cannot fix. Removing "pausa acá" leaves the words
 * the speaker was correcting, so the join is [wrong phrase] → [jump] → [right
 * phrase]: the marker is gone and the sentence still does not cohere. The marker
 * is the one bounded thing in a recording; the take before it is not, which is why
 * {@see SpeechDisfluencyDetector} cuts only the marker and this class exists
 * separately.
 *
 * **The walk-back stops on evidence, never on a guess.** Going back from the
 * anchor it stops at the first of:
 *
 *  - a word the transcriber wrote with trailing sentence punctuation — it decided
 *    a sentence closed there, which is the strongest evidence available without a
 *    model;
 *  - a pause of at least `boundaryGapMs`, meaning the speaker had finished a
 *    thought before starting the one they dropped;
 *  - `maxLookbackMs`, after which the words belong to the previous idea;
 *  - the start of the recording.
 *
 * Every one of those stops makes the proposal SHORTER. That asymmetry is the
 * whole safety argument: the failure this class must never cause is swallowing
 * the opening of the *correct* sentence, so all four exits err towards proposing
 * too little, and {@see FailedTakeBoundary} records which one applied so the
 * owner can check it instead of trusting it.
 *
 * **The anchor is whichever signal found the restart** — a spoken pause marker, or
 * a clause repeated after a hesitation
 * ({@see RestartDetector}, for the recordings where the marker never reached the
 * transcript at all). This class never needs to know which: it only measures
 * backwards from one. That is what lets a recording where Whisper dropped half the
 * markers still get its failed takes removed.
 *
 * A take also never reaches back across an anchor already handled, and one shorter
 * than `minTakeMs` is not proposed at all — a stray "y" is not a restarted
 * sentence, and offering to delete it would only teach the owner to approve
 * blindly.
 *
 * Pure: words and anchors in, takes out. No FFmpeg, no HTTP, no database — every
 * rule here is unit-tested against a hand-written transcript.
 */
final readonly class FailedTakeDetector
{
    public function __construct(
        private int $boundaryGapMs,
        private int $maxLookbackMs,
        private int $minTakeMs,
    ) {}

    /**
     * @param  list<TranscriptWord>  $words  the whole transcript, sorted by start time
     * @param  list<FailedTakeAnchor>  $anchors  where each abandoned attempt ended
     * @return list<FailedTake>
     */
    #[\NoDiscard]
    public function detect(array $words, array $anchors): array
    {
        if ($words === [] || $anchors === []) {
            return [];
        }

        $indexByStartMs = [];

        foreach ($words as $index => $word) {
            $indexByStartMs[$word->startMs] ??= $index;
        }

        // Sorted so "no take may reach back across a span already claimed" is one
        // running value instead of a scan per anchor.
        usort(
            $anchors,
            static fn (FailedTakeAnchor $a, FailedTakeAnchor $b): int => [$a->takeEndsBeforeMs, $a->seekFromMs] <=> [$b->takeEndsBeforeMs, $b->seekFromMs],
        );

        $takes = [];
        $claimedUntilMs = 0;

        foreach ($anchors as $anchor) {
            $endIndex = $indexByStartMs[$anchor->takeEndsBeforeMs] ?? null;
            $seekIndex = $indexByStartMs[$anchor->seekFromMs] ?? null;

            if ($endIndex === null || $seekIndex === null) {
                continue;
            }

            [$from, $boundary] = $this->walkBack($words, $seekIndex, $claimedUntilMs);

            $claimedUntilMs = $anchor->seekFromMs;

            $lastIndex = $endIndex - 1;

            // Nothing preceded the anchor: the speaker flagged a mistake without
            // having said the wrong thing first, or repeated themselves with
            // nothing abandoned in between.
            if ($lastIndex < $from) {
                continue;
            }

            $startMs = $words[$from]->startMs;
            $endMs = $words[$lastIndex]->endMs;

            if ($endMs - $startMs < $this->minTakeMs) {
                continue;
            }

            $takes[] = new FailedTake(
                startMs: $startMs,
                endMs: $endMs,
                startWordIndex: $from,
                endWordIndex: $lastIndex,
                boundary: $boundary,
                corroborated: $anchor->corroborated,
            );
        }

        return $takes;
    }

    /**
     * @param  list<TranscriptWord>  $words
     * @return array{0: int, 1: FailedTakeBoundary} the first word of the take and what stopped the walk
     */
    private function walkBack(array $words, int $anchorIndex, int $claimedUntilMs): array
    {
        $from = $anchorIndex;
        $anchorMs = $words[$anchorIndex]->startMs;

        for ($index = $anchorIndex - 1; $index >= 0; $index--) {
            $word = $words[$index];

            if ($word->startMs < $claimedUntilMs) {
                return [$anchorIndex, FailedTakeBoundary::RecordingStart];
            }

            if (self::closesSentence($word)) {
                return [$index + 1, FailedTakeBoundary::Sentence];
            }

            // The silence in front of the anchor is the hesitation that FOLLOWS the
            // take — it says nothing about where the take began, and reading it as a
            // boundary would make every take zero words long. Only gaps between
            // words already accepted into the take count, which is why the first
            // step inwards never consults one.
            if ($index < $anchorIndex - 1
                && $words[$index + 1]->startMs - $word->endMs >= $this->boundaryGapMs) {
                return [$index + 1, FailedTakeBoundary::Gap];
            }

            if ($anchorMs - $word->startMs > $this->maxLookbackMs) {
                return [$index + 1, FailedTakeBoundary::LookbackLimit];
            }

            $from = $index;
        }

        return [$from, FailedTakeBoundary::RecordingStart];
    }

    /**
     * Trailing sentence punctuation is the transcriber's own judgement that a
     * sentence closed on this word.
     *
     * Only punctuation at the END of the token counts, which is where a sentence
     * boundary lives and where "3.5" or "v1.2" is not one. Over-detecting here is
     * safe: a false boundary shortens the take, and the check runs before the word
     * is allowed into it.
     */
    private static function closesSentence(TranscriptWord $word): bool
    {
        return preg_match('/[.!?…]["\'”’»)\]]*$/u', rtrim($word->text)) === 1;
    }
}
