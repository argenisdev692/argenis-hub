<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\Services;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionRejectionReason;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\ValueObjects\AppliedCut;
use Modules\VideoEdits\Domain\ValueObjects\CutPlan;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Modules\VideoEdits\Domain\ValueObjects\ValidatedDecision;

/**
 * Turns validated decisions into the cut plan the renderer consumes (FR-5).
 *
 * Steps, in order: keep a speech margin inside every silence (D5) → merge
 * overlapping / touching cuts, uniting reasons and origins (D8) → absorb kept
 * fragments too short to be worth keeping (D6) → refuse plans that leave less
 * than the minimum output (D6) → derive the complementary keep ranges.
 * Adapted from GUIDE/VideoExport CutPlanner, now in milliseconds.
 */
final readonly class CutPlanner
{
    public function __construct(
        private int $silencePaddingMs,
        private int $minKeptFragmentMs,
        private int $minOutputMs,
    ) {}

    /**
     * @param  list<ValidatedDecision>  $decisions
     *
     * @throws InvalidCutRangesException when the plan would leave less than the minimum output
     */
    #[\NoDiscard]
    public function plan(array $decisions, int $durationMs): CutPlan
    {
        [$candidates, $decisions] = $this->candidatesFrom($decisions);

        $cuts = $durationMs > 0
            ? $this->absorbShortFragments($this->mergeOverlapping($candidates), $durationMs)
            : [];

        $removedMs = array_sum(array_map(static fn (array $cut): int => $cut['end'] - $cut['start'], $cuts));
        $finalDurationMs = $durationMs - $removedMs;

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
        );
    }

    /**
     * @param  list<ValidatedDecision>  $decisions
     * @return array{0: list<array{start: int, end: int, reasons: list<CutReason>, origins: list<DecisionOrigin>, decisions: list<int>}>, 1: list<ValidatedDecision>}
     */
    private function candidatesFrom(array $decisions): array
    {
        $candidates = [];

        foreach ($decisions as $index => $validated) {
            if (! $validated->isApplied()) {
                continue;
            }

            $decision = $validated->decision;
            $padding = $decision->reason === CutReason::Silence ? $this->silencePaddingMs : 0;
            $start = $decision->startMs + $padding;
            $end = $decision->endMs - $padding;

            if ($end <= $start) {
                $decisions[$index] = ValidatedDecision::rejected($decision, DecisionRejectionReason::ShorterThanPadding);

                continue;
            }

            $candidates[] = [
                'start' => $start,
                'end' => $end,
                'reasons' => [$decision->reason],
                'origins' => [$decision->origin],
                'decisions' => [$index],
            ];
        }

        usort($candidates, static fn (array $a, array $b): int => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);

        return [$candidates, $decisions];
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
