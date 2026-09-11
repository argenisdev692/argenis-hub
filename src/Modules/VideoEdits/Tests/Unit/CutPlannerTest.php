<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionRejectionReason;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\Services\CutPlanner;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Modules\VideoEdits\Domain\ValueObjects\ValidatedDecision;

function planner(): CutPlanner
{
    return new CutPlanner(silencePaddingMs: 150, minKeptFragmentMs: 250, minOutputMs: 1_000);
}

function silence(int $startMs, int $endMs): ValidatedDecision
{
    return ValidatedDecision::applied(new CutDecision('silence_detector', CutReason::Silence, DecisionOrigin::SystemDetection, $startMs, $endMs));
}

function manualCut(int $startMs, int $endMs): ValidatedDecision
{
    return ValidatedDecision::applied(new CutDecision('manual', CutReason::Manual, DecisionOrigin::User, $startMs, $endMs));
}

/**
 * @param  list<TimeRange>  $ranges
 * @return list<array{0: int, 1: int}>
 */
function bounds(array $ranges): array
{
    return array_map(static fn (TimeRange $range): array => [$range->startMs, $range->endMs], $ranges);
}

it('keeps the whole video when nothing is cut', function (): void {
    $plan = planner()->plan([], 60_000);

    expect($plan->hasCuts())->toBeFalse()
        ->and(bounds($plan->keepRanges))->toBe([[0, 60_000]])
        ->and($plan->finalDurationMs)->toBe(60_000)
        ->and($plan->removedDurationMs())->toBe(0);
});

it('keeps a speech margin inside every removed silence', function (): void {
    $plan = planner()->plan([silence(10_000, 12_000)], 60_000);

    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[10_150, 11_850]])
        ->and(bounds($plan->keepRanges))->toBe([[0, 10_150], [11_850, 60_000]])
        ->and($plan->removedDurationMs())->toBe(1_700);
});

it('never pads manual ranges', function (): void {
    $plan = planner()->plan([manualCut(10_000, 12_000)], 60_000);

    expect($plan->appliedCuts[0]->range->startMs)->toBe(10_000)
        ->and($plan->appliedCuts[0]->range->endMs)->toBe(12_000);
});

it('rejects silences too short to keep the margin on both sides', function (): void {
    $plan = planner()->plan([silence(5_000, 5_300)], 60_000);

    expect($plan->hasCuts())->toBeFalse()
        ->and($plan->decisions[0]->isApplied())->toBeFalse()
        ->and($plan->decisions[0]->rejectionReason)->toBe(DecisionRejectionReason::ShorterThanPadding)
        ->and($plan->rejectedDecisionCount())->toBe(1);
});

it('merges overlapping and touching cuts and keeps every reason and origin', function (): void {
    $plan = planner()->plan([
        silence(10_000, 13_000),      // → 10 150 – 12 850
        manualCut(12_500, 15_000),    // overlaps the padded silence
        manualCut(15_000, 16_000),    // touches the previous cut
    ], 60_000);

    expect($plan->appliedCuts)->toHaveCount(1)
        ->and(bounds([$plan->appliedCuts[0]->range]))->toBe([[10_150, 16_000]])
        ->and($plan->appliedCuts[0]->reasons)->toBe([CutReason::Silence, CutReason::Manual])
        ->and($plan->appliedCuts[0]->origins)->toBe([DecisionOrigin::SystemDetection, DecisionOrigin::User])
        ->and(array_map(static fn ($d) => $d->appliedCutSequence, $plan->decisions))->toBe([1, 1, 1]);
});

it('absorbs kept fragments shorter than the minimum, including at both edges', function (): void {
    $plan = planner()->plan([
        manualCut(100, 5_000),        // leaves 100 ms at the start → absorbed
        manualCut(5_200, 8_000),      // 200 ms gap → absorbed into one cut
        manualCut(20_000, 59_900),    // leaves 100 ms at the end → absorbed
    ], 60_000);

    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[0, 8_000], [20_000, 60_000]])
        ->and(bounds($plan->keepRanges))->toBe([[8_000, 20_000]])
        ->and($plan->finalDurationMs)->toBe(12_000);
});

it('numbers applied cuts in timeline order regardless of decision order', function (): void {
    $plan = planner()->plan([manualCut(30_000, 31_000), manualCut(1_000, 2_000)], 60_000);

    expect(array_map(static fn ($cut) => $cut->sequence, $plan->appliedCuts))->toBe([1, 2])
        ->and($plan->appliedCuts[0]->range->startMs)->toBe(1_000)
        ->and($plan->decisions[0]->appliedCutSequence)->toBe(2)
        ->and($plan->decisions[1]->appliedCutSequence)->toBe(1);
});

it('passes rejected decisions through untouched', function (): void {
    $rejected = ValidatedDecision::rejected(
        new CutDecision('manual', CutReason::Manual, DecisionOrigin::User, 70_000, 71_000),
        DecisionRejectionReason::EndBeyondDuration,
    );

    $plan = planner()->plan([$rejected, manualCut(1_000, 2_000)], 60_000);

    expect($plan->decisions[0])->toBe($rejected)
        ->and($plan->appliedCuts)->toHaveCount(1);
});

it('refuses plans that leave less than the minimum output', function (): void {
    (void) planner()->plan([manualCut(0, 59_500)], 60_000);
})->throws(InvalidCutRangesException::class);

it('reports the output problem as user-safe details', function (): void {
    try {
        (void) planner()->plan([manualCut(0, 59_500)], 60_000);
    } catch (InvalidCutRangesException $exception) {
        // The 500 ms tail is longer than the 250 ms fragment floor, so it is kept — and still too short.
        expect($exception->details)->toBe(['output' => ['final_duration_ms' => 500, 'minimum_duration_ms' => 1_000]]);

        return;
    }

    throw new RuntimeException('Expected InvalidCutRangesException.');
});
