<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\ValueObjects\Restart;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * Finds abandoned takes by the repetition that follows them, with no reliance on
 * a spoken marker.
 *
 * OBSERVACIONES round 3: "marcas de pausa acá y tomas falsas que AÚN se
 * escuchan". The marker half of that has a cause no amount of tuning fixes — in
 * this application's own transcripts Whisper wrote the marker as a word in some
 * takes and omitted it entirely in others, so a dictionary matched it roughly half
 * the time and the rest of the recordings kept "pausa acá" in the delivered audio.
 * The prompt sent to Whisper cannot close that gap: OpenAI documents `prompt` as a
 * spelling dictionary, not a style control.
 *
 * **The repetition is the reliable half.** A speaker who drops an attempt says the
 * next thing again from the top, and that is in the transcript either way. This
 * class therefore treats a repeated clause as the *boundary evidence* for a failed
 * take, feeding the same walk-back
 * {@see FailedTakeDetector} already performs for a marker.
 *
 * **The discriminator is the gap, and it is the whole design.** Repeating a clause
 * is also how a speaker emphasises one. The two are separated by silence alone: an
 * emphasis repeat follows immediately, an abandoned attempt is preceded by a
 * hesitation long enough to hear. `minGapMs` is that threshold.
 *
 * **Collapsing is not optional.** One restart is reported by every phrase length
 * that happens to fit inside it: in this application's own transcript a single
 * restart at 2:10 produced hits at 4, 5 and 6 words with three different start
 * times, so without clustering one restart becomes three overlapping proposals
 * and the owner is asked to approve the same cut three times. Candidates whose
 * second copy starts within `clusterMs` of each other are one restart, and the
 * one carrying the earliest failed attempt wins — it is the longest span and so
 * the only one that can reach a real boundary.
 *
 * **Refrains are skipped on purpose.** A phrase said three or more times is a
 * refrain or an artefact of sliding windows, not a restart, so a clause with more
 * than two copies is ignored. Missing a restart costs the owner one unticked box;
 * proposing a refrain for deletion costs them a word they meant.
 *
 * Pure: words in, restarts out.
 */
final readonly class RestartDetector
{
    /**
     * Words that carry no meaning on their own. A repeated run made only of these
     * is grammar, not a clause — "de la que con el" recurring says nothing.
     *
     * @var list<string>
     */
    private const array CONTENT_STOPWORDS = [
        'este', 'esta', 'esto', 'ese', 'esa', 'eso', 'aqui', 'alla', 'ahi',
        'como', 'cuando', 'donde', 'mientras', 'porque', 'aunque', 'tambien',
        'sino', 'pero', 'para', 'como', 'entonces', 'ahora', 'luego', 'despues',
        'mismo', 'misma', 'todo', 'toda', 'todos', 'todas', 'otro', 'otra',
        'nada', 'algo', 'alguien', 'cada', 'cunto', 'segun', 'sin',
    ];

    public function __construct(
        private int $minPhraseWords,
        private int $maxPhraseWords,
        private int $minGapMs,
        private int $maxRepeatDistanceMs,
        private int $clusterMs,
        /**
         * How many words may sit between the two copies.
         *
         * **This is what separates a restart from a callback**, and the silence
         * alone is not enough: a clause restated later in the same recording is
         * also preceded by a long gap, because a whole sentence was said in
         * between. What makes a restart is that the interlude is SHORT — the
         * speaker stopped, said the marker, and started again.
         *
         * Measured against this application's own transcript: a real restart at
         * 2:10 has exactly two words between the copies, because Whisper garbled
         * the spoken "pausa acá" into "vos acá" rather than dropping it. Eight
         * leaves room for a marker plus a few fillers while still excluding a
         * callback, where the intervening clause is a sentence at minimum.
         */
        private int $maxInterludeWords,
    ) {}

    /**
     * @param  list<TranscriptWord>  $words  the whole transcript, sorted by start time
     * @return list<Restart>
     */
    #[\NoDiscard]
    public function detect(array $words): array
    {
        if ($words === [] || $this->minPhraseWords < 2 || $this->minPhraseWords > $this->maxPhraseWords) {
            return [];
        }

        $tokens = array_map(static fn (TranscriptWord $word): string => $word->normalized(), $words);
        $count = count($words);

        // Keyed by the pair of positions, keeping the LONGEST phrase that matches
        // at both. Matching every length separately and testing each one is what
        // breaks on an immediate repeat: "este es el paso" matches at four words
        // with the rest of the phrase sitting between the two copies, so the
        // interlude looks short and the elapsed time looks long, and it reads as a
        // restart. Taking the maximal match instead makes the interlude zero and
        // the elapsed time a fraction of a second, which is an emphasis repeat and
        // is rejected on the gap.
        $pairs = [];

        for ($length = $this->minPhraseWords; $length <= min($this->maxPhraseWords, $count); $length++) {
            foreach ($this->phrases($tokens, $length) as $positions) {
                $pairs[$positions[0].':'.$positions[1]] = [
                    'first' => $positions[0],
                    'second' => $positions[1],
                    'length' => $length,
                ];
            }
        }

        $pairs = $this->dropSubPhrases($pairs);

        $candidates = [];

        foreach ($pairs as $pair) {
            $first = $pair['first'];
            $second = $pair['second'];
            $length = $pair['length'];

            // The hesitation the listener hears runs from the end of the abandoned
            // copy to the start of the restart, marker and all — not from the last
            // word, because the words the transcriber put in that gap are the
            // marker the speaker actually said.
            $gapMs = $words[$second]->startMs - $words[$first + $length - 1]->endMs;

            if ($gapMs < $this->minGapMs) {
                continue;
            }

            // Measured against this module's own transcript: a real restart has
            // fourteen words between the two copies, because the speaker kept
            // talking after the repeated clause before giving up. A ceiling much
            // above that would exclude genuine restarts; far below it would start
            // admitting callbacks.
            if ($second - ($first + $length) > $this->maxInterludeWords) {
                continue;
            }

            if ($words[$second]->startMs - $words[$first]->startMs > $this->maxRepeatDistanceMs) {
                continue;
            }

            $candidates[] = new Restart(
                failedStartMs: $words[$first]->startMs,
                restartStartMs: $words[$second]->startMs,
                gapMs: $gapMs,
                text: implode(' ', array_slice($tokens, $first, $length)),
            );
        }

        return $this->collapse($candidates);
    }

    /**
     * Removes a candidate that is a fragment of a longer repeat at the same offset.
     *
     * Without this, an immediately repeated eight-word clause produces a candidate
     * for the eight words AND for every sub-phrase of them — "el paso que casi
     * nadie da" sits inside "este es el paso que casi nadie da", offset by the same
     * eight words, so it looks like a restart with a long hesitation when the real
     * gap between the copies is fifty milliseconds. The longer match is the true
     * one, and it is rejected on its own gap; this stops the fragments from
     * sneaking past the test it failed.
     *
     * Same offset is the whole condition. Two repeats at different offsets are two
     * genuinely different spans.
     *
     * @param  list<array{first: int, second: int, length: int}>  $pairs
     * @return list<array{first: int, second: int, length: int}>
     */
    private function dropSubPhrases(array $pairs): array
    {
        $kept = [];

        foreach ($pairs as $pair) {
            $alignment = $pair['second'] - $pair['first'];
            $contained = false;

            foreach ($pairs as $other) {
                if ($other === $pair) {
                    continue;
                }

                if ($alignment !== $other['second'] - $other['first']) {
                    continue;
                }

                if ($other['first'] < $pair['first']
                    && $other['first'] + $other['length'] >= $pair['first'] + $pair['length']) {
                    $contained = true;

                    break;
                }
            }

            if (! $contained) {
                $kept[] = $pair;
            }
        }

        return $kept;
    }

    /**
     * Every phrase of `$length` tokens that occurs exactly twice, as the pair of
     * positions holding its two copies.
     *
     * Phrases with no content word are dropped before indexing, so the map never
     * fills with grammatical filler that can never be a clause.
     *
     * @param  list<string>  $tokens
     * @return list<array{int, int}>
     */
    private function phrases(array $tokens, int $length): array
    {
        /** @var array<string, list{int}> $seen */
        $seen = [];
        $total = count($tokens);

        for ($index = 0; $index + $length <= $total; $index++) {
            $slice = array_slice($tokens, $index, $length);

            if (in_array('', $slice, true)) {
                continue;
            }

            if (! $this->hasContentWord($slice)) {
                continue;
            }

            $seen[implode(' ', $slice)][] = $index;
        }

        return array_values(array_map(
            static fn (array $positions): array => $positions,
            array_filter($seen, static fn (array $positions): bool => count($positions) === 2),
        ));
    }

    /**
     * @param  list<string>  $tokens
     */
    private function hasContentWord(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (mb_strlen($token) >= 4 && ! in_array($token, self::CONTENT_STOPWORDS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One restart, one proposal.
     *
     * Sorted by where the restart begins so a cluster is a run of candidates
     * whose second copies are within `clusterMs` of each other; the earliest
     * failed attempt in the cluster is the one kept, because the longest span is
     * the only one that can reach back to a real boundary.
     *
     * @param  list<Restart>  $candidates
     * @return list<Restart>
     */
    private function collapse(array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        usort(
            $candidates,
            static fn (Restart $a, Restart $b): int => [$a->restartStartMs, $a->failedStartMs] <=> [$b->restartStartMs, $b->failedStartMs],
        );

        $clusters = [];
        $current = [$candidates[0]];

        foreach (array_slice($candidates, 1) as $candidate) {
            $anchor = $current[0]->restartStartMs;

            if ($candidate->restartStartMs - $anchor <= $this->clusterMs) {
                $current[] = $candidate;

                continue;
            }

            $clusters[] = self::earliest($current);
            $current = [$candidate];
        }

        $clusters[] = self::earliest($current);

        return $clusters;
    }

    /**
     * @param  list<Restart>  $cluster
     */
    private static function earliest(array $cluster): Restart
    {
        usort($cluster, static fn (Restart $a, Restart $b): int => $a->failedStartMs <=> $b->failedStartMs);

        return $cluster[0];
    }
}
