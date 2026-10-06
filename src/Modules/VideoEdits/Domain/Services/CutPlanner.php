<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionRejectionReason;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\ValueObjects\AppliedCut;
use Modules\VideoEdits\Domain\ValueObjects\CompressedRange;
use Modules\VideoEdits\Domain\ValueObjects\CutPlan;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptSegment;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Modules\VideoEdits\Domain\ValueObjects\ValidatedDecision;
use Modules\VideoEdits\Domain\ValueObjects\WaitCompressionPolicy;

/**
 * Turns validated decisions into the cut plan the renderer consumes (FR-5).
 *
 * Steps, in order: keep a speech margin inside every silence (D5), wider in
 * the middle of a sentence → keep silence cuts off every transcribed word →
 * set aside silences where the screen was busy (tool waits) → trim every other
 * cut back off those waits, so nothing splices into a tool mid-work → merge
 * overlapping / touching cuts, uniting reasons and origins (D8) → absorb kept
 * fragments too short to be worth keeping (D6) → speed up the tool waits that
 * survived the cuts → turn down the breaths between words → refuse plans that
 * leave less than the minimum output (D6) → derive the complementary keep
 * ranges. Adapted from GUIDE/VideoExport CutPlanner, now in milliseconds.
 */
final readonly class CutPlanner
{
    /** A segment ending this close to a silence is the sentence that silence follows. */
    private const int SENTENCE_END_TOLERANCE_MS = 600;

    /** Shortest breath worth turning down: below this the ramps are all there is. */
    private const int MIN_ATTENUATION_MS = 120;

    /**
     * The speech margin is asymmetric on purpose: a Spanish word decays into
     * its unstressed last syllable ("compar-TIR", "herramien-TA"), which drops
     * under the silence floor while still audible, whereas an attack is sharp.
     * So the margin after speech is wider than the margin before it.
     *
     * The word guards apply only when a transcript exists: no silence cut may
     * reach into `[word.start - wordGuardBeforeMs, word.end + wordGuardAfterMs]`,
     * which also absorbs Whisper's own drift on word edges.
     *
     * `midSentencePauseMs` keeps a breath of air when a pause is cut inside a
     * sentence: removing it entirely glues two halves of one intonation
     * together, which is what makes a jump cut sound robotic. `breathMinGapMs`
     * is the shortest gap between two words whose sound (a breath) is turned
     * down; 0 disables it.
     */
    public function __construct(
        private int $silencePaddingAfterSpeechMs,
        private int $silencePaddingBeforeSpeechMs,
        private int $wordGuardAfterMs,
        private int $wordGuardBeforeMs,
        private int $minKeptFragmentMs,
        private int $minOutputMs,
        private ?WaitCompressionPolicy $waitCompression = null,
        private int $breathMinGapMs = 0,
        private int $midSentencePauseMs = 0,
        private ?CutEdgeTrimmer $edgeTrimmer = null,
    ) {}

    /**
     * @param  list<ValidatedDecision>  $decisions
     * @param  list<TranscriptWord>  $words  transcript of the same working file; empty when none was made
     * @param  list<TranscriptSegment>  $segments  the same transcript's segments, for sentence boundaries
     *
     * @throws InvalidCutRangesException when the plan would leave less than the minimum output
     */
    #[\NoDiscard]
    public function plan(array $decisions, int $durationMs, array $words = [], array $segments = []): CutPlan
    {
        [$candidates, $waits, $decisions] = $this->candidatesFrom($decisions, $words, $segments);

        $cuts = $durationMs > 0
            ? $this->absorbShortFragments($this->mergeOverlapping($candidates), $durationMs)
            : [];

        $cutZones = array_map(static fn (array $cut): array => [$cut['start'], $cut['end']], $cuts);
        $compressedRanges = $this->compressWaits($waits, $cutZones);
        $attenuatedRanges = $this->breathRanges($words, $cutZones, $compressedRanges);

        $removedMs = array_sum(array_map(static fn (array $cut): int => $cut['end'] - $cut['start'], $cuts));
        $savedMs = array_sum(array_map(static fn (CompressedRange $range): int => $range->savedMs(), $compressedRanges));
        $finalDurationMs = $durationMs - $removedMs - $savedMs;

        if ($finalDurationMs < $this->minOutputMs) {
            throw InvalidCutRangesException::outputTooShort($finalDurationMs, $this->minOutputMs);
        }

        $appliedCuts = [];

        foreach ($cuts as $index => $cut) {
            $sequence = $index + 1;
            $appliedCuts[] = new AppliedCut($sequence, new TimeRange($cut['start'], $cut['end']), $cut['reasons'], $cut['origins']);

            foreach ($cut['decisions'] as $decisionIndex) {
                $decisions[$decisionIndex] = $decisions[$decisionIndex]->withAppliedCutSequence($sequence);
            }
        }

        return new CutPlan(
            appliedCuts: $appliedCuts,
            keepRanges: $this->keepRanges($cuts, $durationMs),
            decisions: $decisions,
            originalDurationMs: $durationMs,
            finalDurationMs: $finalDurationMs,
            compressedRanges: $compressedRanges,
            attenuatedRanges: $attenuatedRanges,
        );
    }

    /**
     * @param  list<ValidatedDecision>  $decisions
     * @param  list<TranscriptWord>  $words
     * @param  list<TranscriptSegment>  $segments
     * @return array{0: list<array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}>, 1: list<array{0: int, 1: int}>, 2: list<ValidatedDecision>}
     */
    private function candidatesFrom(array $decisions, array $words, array $segments): array
    {
        $candidates = [];
        $waits = [];
        // The trimmer walks the transcript backwards from each cut, so it needs
        // one ordering shared by every decision rather than its own per call.
        usort($words, static fn (TranscriptWord $a, TranscriptWord $b): int => [$a->startMs, $a->endMs] <=> [$b->startMs, $b->endMs]);
        $speechZones = $this->speechZones($words);
        $waitCompression = $this->waitCompression ?? WaitCompressionPolicy::disabled();

        foreach ($decisions as $index => $validated) {
            if (! $validated->isApplied()) {
                continue;
            }

            $decision = $validated->decision;
            $ranges = [[$decision->startMs, $decision->endMs]];

            if ($decision->reason === CutReason::Silence) {
                $afterSpeechMs = $this->midSentencePauseMs > 0 && $segments !== [] && ! self::endsSentenceAt($decision->startMs, $segments)
                    ? max($this->silencePaddingAfterSpeechMs, $this->midSentencePauseMs)
                    : $this->silencePaddingAfterSpeechMs;
                $start = $decision->startMs + $afterSpeechMs;
                $end = $decision->endMs - $this->silencePaddingBeforeSpeechMs;

                if ($end <= $start) {
                    $decisions[$index] = ValidatedDecision::rejected($decision, DecisionRejectionReason::ShorterThanPadding);

                    continue;
                }

                $ranges = self::subtract($start, $end, $speechZones);

                if ($ranges === []) {
                    $decisions[$index] = ValidatedDecision::rejected($decision, DecisionRejectionReason::OverlapsSpeech);

                    continue;
                }

                $screenActivity = $decision->evidence['screen_activity'] ?? null;

                if ($waitCompression->appliesTo(is_numeric($screenActivity) ? (float) $screenActivity : null)) {
                    array_push($waits, ...$ranges);
                    $decisions[$index] = ValidatedDecision::rejected($decision, DecisionRejectionReason::ScreenActivity);

                    continue;
                }
            } elseif ($this->edgeTrimmer !== null && $words !== [] && $decision->reason !== CutReason::Manual) {
                // Widening happens before the tool-wait guard below so the guard
                // sees the final range and can still refuse a cut that now reaches
                // into a wait. Manual ranges are the owner's own call and are left
                // exactly as they were typed.
                $ranges = array_map(
                    fn (array $range): array => $this->edgeTrimmer->absorbDanglingEdges($range[0], $range[1], $words),
                    $ranges,
                );
            }

            foreach ($ranges as [$start, $end]) {
                $candidates[] = [
                    'start' => $start,
                    'end' => $end,
                    'reasons' => [$decision->reason],
                    'origins' => [$decision->origin],
                    'decisions' => [$index],
                ];
            }
        }

        usort($candidates, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        // The waits are only known once every decision has been read, so the
        // guard runs as its own pass over the candidates it just produced.
        [$candidates, $decisions] = $this->keepCutsOutOfWaits($candidates, $waits, $decisions);

        return [$candidates, $waits, $decisions];
    }

    /**
     * Keeps non-silence cuts out of the tool waits.
     *
     * A silence with a busy screen is already upgraded to a sped-up stretch, so
     * nothing is lost there — but the other producers can still land a cut on
     * top of one: a stutter detected while an AI streams its answer, or a
     * "PAUSA" flagged mid-generation. That is the abrupt splice a reviewer
     * complains about, because the picture jumps in the middle of the tool
     * working and the result appears with nothing before it. Every such cut is
     * trimmed back to the wait's edges; one swallowed whole is rejected outright
     * rather than quietly shrunk to nothing.
     *
     * Manual ranges are exempt: marking a range by hand is the owner saying they
     * looked at it and meant it, which outranks any default.
     *
     * @param  list<array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}>  $candidates
     * @param  list<array{0: int, 1: int}>  $waits
     * @param  list<ValidatedDecision>  $decisions
     * @return array{0: list<array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}>, 1: list<ValidatedDecision>}
     */
    private function keepCutsOutOfWaits(array $candidates, array $waits, array $decisions): array
    {
        if ($candidates === [] || $waits === []) {
            return [$candidates, $decisions];
        }

        usort($waits, static fn (array $a, array $b): int => $a <=> $b);

        $kept = [];
        $rejected = [];

        foreach ($candidates as $candidate) {
            if (in_array(CutReason::Manual, $candidate['reasons'], true)) {
                $kept[] = $candidate;

                continue;
            }

            $remaining = self::subtract($candidate['start'], $candidate['end'], $waits);

            if ($remaining === []) {
                foreach ($candidate['decisions'] as $index) {
                    $rejected[$index] = true;
                }

                continue;
            }

            foreach ($remaining as [$start, $end]) {
                $kept[] = ['start' => $start, 'end' => $end] + $candidate;
            }
        }

        foreach (array_keys($rejected) as $index) {
            $decisions[$index] = ValidatedDecision::rejected(
                $decisions[$index]->decision,
                DecisionRejectionReason::ScreenBusy,
            );
        }

        usort($kept, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        return [$kept, $decisions];
    }

    /**
     * True when a transcript segment that closes a sentence ends right where
     * the silence begins. Whisper also breaks segments at mid-sentence pauses,
     * so the punctuation is what decides, not the segment boundary alone.
     *
     * @param  list<TranscriptSegment>  $segments
     */
    private static function endsSentenceAt(int $silenceStartMs, array $segments): bool
    {
        foreach ($segments as $segment) {
            if (abs($segment->endMs - $silenceStartMs) <= self::SENTENCE_END_TOLERANCE_MS
                && preg_match('/[.?!…:;]["»”\')\]]*$/u', trim($segment->text)) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tool waits minus whatever a cut removes anyway; each surviving stretch
     * long enough to matter gets a sped-up middle.
     *
     * @param  list<array{0: int, 1: int}>  $waits
     * @param  list<array{0: int, 1: int}>  $cutZones  sorted by start
     * @return list<CompressedRange>
     */
    private function compressWaits(array $waits, array $cutZones): array
    {
        $waitCompression = $this->waitCompression ?? WaitCompressionPolicy::disabled();
        $compressed = [];

        foreach ($waits as [$start, $end]) {
            foreach (self::subtract($start, $end, $cutZones) as [$pieceStart, $pieceEnd]) {
                $range = $waitCompression->compress($pieceStart, $pieceEnd);

                if ($range !== null) {
                    $compressed[] = $range;
                }
            }
        }

        usort($compressed, static fn (CompressedRange $a, CompressedRange $b): int => $a->range->startMs <=> $b->range->startMs);

        return $compressed;
    }

    /**
     * The gaps between consecutive words, kept clear of the words themselves by
     * the same guards the silence cuts use. What sits there is breath, lip noise
     * or room tone — turned down, never cut: removing every breath is what makes
     * an edit sound robotic.
     *
     * @param  list<TranscriptWord>  $words
     * @param  list<array{0: int, 1: int}>  $cutZones
     * @param  list<CompressedRange>  $compressedRanges
     * @return list<TimeRange>
     */
    private function breathRanges(array $words, array $cutZones, array $compressedRanges): array
    {
        if ($this->breathMinGapMs <= 0 || count($words) < 2) {
            return [];
        }

        $blocked = [
            ...$cutZones,
            ...array_map(static fn (CompressedRange $range): array => [$range->range->startMs, $range->range->endMs], $compressedRanges),
        ];
        usort($blocked, static fn (array $a, array $b): int => $a <=> $b);

        usort($words, static fn (TranscriptWord $a, TranscriptWord $b): int => $a->startMs <=> $b->startMs);
        $ranges = [];

        for ($index = 1, $count = count($words); $index < $count; $index++) {
            $gapStart = $words[$index - 1]->endMs + $this->wordGuardAfterMs;
            $gapEnd = $words[$index]->startMs - $this->wordGuardBeforeMs;

            if ($gapEnd - $gapStart < $this->breathMinGapMs) {
                continue;
            }

            foreach (self::subtract($gapStart, $gapEnd, $blocked) as [$start, $end]) {
                if ($end - $start >= self::MIN_ATTENUATION_MS) {
                    $ranges[] = new TimeRange($start, $end);
                }
            }
        }

        return $ranges;
    }

    /**
     * Only silence cuts are kept off words: filler, stutter, AI and manual cuts
     * remove words on purpose.
     *
     * @param  list<TranscriptWord>  $words
     * @return list<array{0: int, 1: int}>
     */
    private function speechZones(array $words): array
    {
        $zones = array_map(
            fn (TranscriptWord $word): array => [
                max(0, $word->startMs - $this->wordGuardBeforeMs),
                $word->endMs + $this->wordGuardAfterMs,
            ],
            $words,
        );

        usort($zones, static fn (array $a, array $b): int => $a <=> $b);

        return $zones;
    }

    /**
     * `[start, end)` minus every speech zone. A silence ffmpeg heard inside a
     * word Whisper transcribed (a quiet word under the floor) splits in two
     * instead of swallowing the word.
     *
     * @param  list<array{0: int, 1: int}>  $zones  sorted by start
     * @return list<array{0: int, 1: int}>
     */
    private static function subtract(int $start, int $end, array $zones): array
    {
        $ranges = [];
        $cursor = $start;

        foreach ($zones as [$zoneStart, $zoneEnd]) {
            if ($zoneEnd <= $cursor) {
                continue;
            }

            if ($zoneStart >= $end) {
                break;
            }

            if ($zoneStart > $cursor) {
                $ranges[] = [$cursor, $zoneStart];
            }

            $cursor = $zoneEnd;

            if ($cursor >= $end) {
                return $ranges;
            }
        }

        $ranges[] = [$cursor, $end];

        return $ranges;
    }

    /**
     * @param  list<array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}>  $candidates
     * @return list<array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}>
     */
    private function mergeOverlapping(array $candidates): array
    {
        $merged = [];

        foreach ($candidates as $candidate) {
            $last = array_key_last($merged);

            if ($last !== null && $candidate['start'] <= $merged[$last]['end']) {
                $merged[$last] = $this->union($merged[$last], $candidate);

                continue;
            }

            $merged[] = $candidate;
        }

        return $merged;
    }

    /**
     * @param  list<array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}>  $cuts
     * @return list<array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}>
     */
    private function absorbShortFragments(array $cuts, int $durationMs): array
    {
        if ($cuts === []) {
            return [];
        }

        $absorbed = [];

        foreach ($cuts as $cut) {
            $last = array_key_last($absorbed);

            if ($last !== null && $this->minKeptFragmentMs > $cut['start'] - $absorbed[$last]['end']) {
                $absorbed[$last] = $this->union($absorbed[$last], $cut);

                continue;
            }

            $absorbed[] = $cut;
        }

        if ($absorbed[0]['start'] < $this->minKeptFragmentMs) {
            $absorbed[0]['start'] = 0;
        }

        $last = array_key_last($absorbed);

        if ($durationMs - $absorbed[$last]['end'] < $this->minKeptFragmentMs) {
            $absorbed[$last]['end'] = $durationMs;
        }

        return $absorbed;
    }

    /**
     * @param  array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}  $into
     * @param  array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}  $other
     * @return array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}
     */
    private function union(array $into, array $other): array
    {
        return [
            'start' => min($into['start'], $other['start']),
            'end' => max($into['end'], $other['end']),
            'reasons' => self::uniqueEnums([...$into['reasons'], ...$other['reasons']]),
            'origins' => self::uniqueEnums([...$into['origins'], ...$other['origins']]),
            'decisions' => [...$into['decisions'], ...$other['decisions']],
        ];
    }

    /**
     * @param  list<array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}>  $cuts
     * @return list<TimeRange>
     */
    private function keepRanges(array $cuts, int $durationMs): array
    {
        $keep = [];
        $cursor = 0;

        foreach ($cuts as $cut) {
            if ($cut['start'] > $cursor) {
                $keep[] = new TimeRange($cursor, $cut['start']);
            }

            $cursor = $cut['end'];
        }

        if ($durationMs > $cursor) {
            $keep[] = new TimeRange($cursor, $durationMs);
        }

        return $keep;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param  list<T>  $cases
     * @return list<T>
     */
    private static function uniqueEnums(array $cases): array
    {
        $unique = [];

        foreach ($cases as $case) {
            $unique[$case->value] ??= $case;
        }

        return array_values($unique);
    }
}
