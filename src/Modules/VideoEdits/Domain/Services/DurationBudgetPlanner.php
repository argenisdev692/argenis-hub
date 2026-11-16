<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\ValueObjects\DurationPlan;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * Answers "how do I get from 13:42 to 10:00" with arithmetic instead of advice.
 *
 * The transcript already holds every word's exact milliseconds, so the dead air
 * between phrases is measured, not guessed. The plan then adds up what the edit
 * is actually going to remove — silence, the deterministic speech cleanup, the
 * manual ranges and the AI's proposals — and compares that with the target.
 *
 * **Every origin counts.** The first version only counted dead air and the
 * model's retakes, and in a real recording those are the minority: most of the
 * removed time is fillers, stutters and the spoken markers. A target that
 * ignored them declared "1:02 short" for a video that would have landed under
 * it — an estimate that is wrong in the direction that costs a re-record.
 *
 * **Ellipses count, and that is deliberate.** `"vamos... a... ingresar"` is a
 * phrase delivered robotically; its gaps are exactly the dead air this reports.
 * Only `…` and terminal punctuation end a sentence here, the same rule
 * {@see FailedTakeDetector} uses, so the two never disagree about where a
 * thought closed.
 *
 * Pure: a transcript and range lists in, a plan out. No FFmpeg, no model, no
 * database — which is also why it costs nothing on the worker: the analysis
 * pass has everything in memory already.
 */
final readonly class DurationBudgetPlanner
{
    /**
     * @param  int  $gapFloorMs  shortest gap worth reporting; below it, a gap is speech rhythm
     * @param  int  $longestGaps  how many gaps travel with the plan, longest first
     */
    public function __construct(
        private int $gapFloorMs = 250,
        private int $longestGaps = 10,
    ) {}

    /**
     * @param  int  $totalMs  the merged recording's duration, not the speech span
     * @param  int|null  $targetMs  null when the owner did not ask for a length
     * @param  list<array{startMs: int, endMs: int}>  $speechCuts  deterministic speech cleanup
     * @param  list<array{startMs: int, endMs: int}>  $aiCuts  proposals the owner approved
     * @param  list<array{startMs: int, endMs: int}>  $manualCuts  ranges the owner drew
     * @param  int  $silenceMs  what silence removal already cuts, measured from its own detections
     */
    #[\NoDiscard]
    public function plan(
        Transcript $transcript,
        int $totalMs,
        ?int $targetMs,
        array $speechCuts = [],
        array $aiCuts = [],
        array $manualCuts = [],
        int $silenceMs = 0,
    ): DurationPlan {
        $words = array_values(array_filter(
            $transcript->words,
            static fn (TranscriptWord $word): bool => ! $word->isBracketedSound(),
        ));

        $gaps = $this->gaps($words);
        $deadAirMs = array_sum(array_column($gaps, 'gap_ms'));
        $sentenceGapMs = array_sum(array_column(
            array_filter($gaps, static fn (array $gap): bool => $gap['after_sentence_end']),
            'gap_ms',
        ));

        $longest = $gaps;
        usort($longest, static fn (array $left, array $right): int => $right['gap_ms'] <=> $left['gap_ms']);

        return new DurationPlan(
            totalMs: max(0, $totalMs),
            targetMs: $targetMs === null ? null : max(0, $targetMs),
            gapCount: count($gaps),
            deadAirMs: $deadAirMs,
            sentenceGapMs: $sentenceGapMs,
            intraPhraseGapMs: $deadAirMs - $sentenceGapMs,
            silenceRecoverableMs: max(0, $silenceMs),
            aiProposalMs: self::totalMs(self::merge($aiCuts)),
            longestGaps: array_slice($longest, 0, max(0, $this->longestGaps)),
            speechCutMs: self::totalMs(self::merge($speechCuts)),
            manualCutMs: self::totalMs(self::merge($manualCuts)),
            plannedCutMs: self::totalMs(self::merge([...$speechCuts, ...$aiCuts, ...$manualCuts])),
        );
    }

    /**
     * Every gap worth reporting, with the evidence that decides its kind.
     *
     * `after_sentence_end` is what separates "the speaker finished a thought"
     * from "the speaker stopped mid-phrase": the first is pacing a person
     * accepts, the second is the robotic delivery the editor complains about.
     *
     * @param  list<TranscriptWord>  $words
     * @return list<array{start_ms: int, end_ms: int, gap_ms: int, after_sentence_end: bool}>
     */
    private function gaps(array $words): array
    {
        $gaps = [];

        for ($index = 1, $count = count($words); $index < $count; $index++) {
            $previous = $words[$index - 1];
            $gapMs = $words[$index]->startMs - $previous->endMs;

            if ($gapMs < $this->gapFloorMs) {
                continue;
            }

            $gaps[] = [
                'start_ms' => $previous->endMs,
                'end_ms' => $words[$index]->startMs,
                'gap_ms' => $gapMs,
                'after_sentence_end' => self::closesSentence($previous),
            ];
        }

        return $gaps;
    }

    /**
     * Merges overlapping ranges, so a second removed by two proposals is still
     * one second — the stutter inside a retake is the common case.
     *
     * @param  list<array{startMs: int, endMs: int}>  $ranges
     * @return list<array{0: int, 1: int}>
     */
    private static function merge(array $ranges): array
    {
        usort($ranges, static fn (array $left, array $right): int => $left['startMs'] <=> $right['startMs']);

        $merged = [];

        foreach ($ranges as $range) {
            if ($range['endMs'] <= $range['startMs']) {
                continue;
            }

            $last = array_key_last($merged);

            if ($last !== null && $range['startMs'] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $range['endMs']);

                continue;
            }

            $merged[] = [$range['startMs'], $range['endMs']];
        }

        return $merged;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $merged
     */
    private static function totalMs(array $merged): int
    {
        return array_sum(array_map(
            static fn (array $range): int => $range[1] - $range[0],
            $merged,
        ));
    }

    /**
     * Trailing sentence punctuation is the transcriber's own judgement that a
     * thought closed there — the same evidence {@see FailedTakeDetector} trusts,
     * so "where the last good phrase ended" means one thing in both places.
     */
    private static function closesSentence(TranscriptWord $word): bool
    {
        return preg_match('/[.!?…]["\'”’»)\]]*$/u', rtrim($word->text)) === 1;
    }
}
