<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Services\DurationBudgetPlanner;
use Modules\VideoEdits\Domain\ValueObjects\DurationPlan;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

function budgetPlanner(): DurationBudgetPlanner
{
    return new DurationBudgetPlanner(gapFloorMs: 250, longestGaps: 10);
}

/**
 * A finished sentence, a phrase with a gap inside it, and a long halt before
 * the closing word — the three kinds of dead air the plan separates.
 *
 * Gaps: 900 ms after "Hoy." (sentence), 500 ms and 1500 ms inside the phrase.
 * The 20 ms and 50 ms joins are rhythm and must not be counted.
 */
function planTranscript(): Transcript
{
    return new Transcript([
        new TranscriptWord('Hoy.', 0, 300),
        new TranscriptWord('vamos', 1_200, 1_500),
        new TranscriptWord('a', 1_520, 1_600),
        new TranscriptWord('ingresar', 2_100, 2_600),
        new TranscriptWord('y', 2_650, 2_700),
        new TranscriptWord('listo', 4_200, 4_500),
    ], language: 'es');
}

it('reports the gap inventory and no verdict when no target was asked for', function (): void {
    $plan = budgetPlanner()->plan(planTranscript(), totalMs: 600_000, targetMs: null);

    expect($plan->verdict())->toBe('no_target')
        ->and($plan->requiredRemovalMs())->toBeNull()
        ->and($plan->shortfallMs())->toBeNull()
        ->and($plan->gapCount)->toBe(3)
        ->and($plan->deadAirMs)->toBe(2_900)
        ->and($plan->sentenceGapMs)->toBe(900)
        ->and($plan->intraPhraseGapMs)->toBe(2_000);
});

it('counts every origin, and the union once', function (): void {
    $plan = budgetPlanner()->plan(
        planTranscript(),
        600_000,
        null,
        speechCuts: [['startMs' => 0, 'endMs' => 1_000]],
        aiCuts: [['startMs' => 500, 'endMs' => 1_500]],
        manualCuts: [['startMs' => 4_000, 'endMs' => 4_500]],
        silenceMs: 2_000,
    );

    // Speech and AI overlap by 500 ms, so their union is 1 500 and not 2 000.
    expect($plan->speechCutMs)->toBe(1_000)
        ->and($plan->aiProposalMs)->toBe(1_000)
        ->and($plan->manualCutMs)->toBe(500)
        ->and($plan->plannedCutMs)->toBe(2_000)
        ->and($plan->silenceRecoverableMs)->toBe(2_000)
        ->and($plan->plannedRemovalMs())->toBe(4_000);
});

it('reports no silence when silence removal produced nothing', function (): void {
    expect(budgetPlanner()->plan(planTranscript(), 600_000, null)->silenceRecoverableMs)->toBe(0);
});

it('names the shortfall when the measured removals cannot reach the target', function (): void {
    // 10:00 → 8:00 needs 120 s and the cuts give 30 s, so 90 s is missing.
    $plan = budgetPlanner()->plan(planTranscript(), 600_000, 480_000, aiCuts: [
        ['startMs' => 0, 'endMs' => 30_000],
    ]);

    expect($plan->requiredRemovalMs())->toBe(120_000)
        ->and($plan->plannedRemovalMs())->toBe(30_000)
        ->and($plan->projectedMs())->toBe(570_000)
        ->and($plan->shortfallMs())->toBe(90_000)
        ->and($plan->verdict())->toBe('short')
        ->and($plan->meetsTarget())->toBeFalse();
});

it('says the target is reachable when the removals cover it', function (): void {
    // 10:00 → 9:30 needs 30 s, and the single 30 s cut covers exactly that.
    $plan = budgetPlanner()->plan(planTranscript(), 600_000, 570_000, aiCuts: [
        ['startMs' => 0, 'endMs' => 30_000],
    ]);

    expect($plan->shortfallMs())->toBe(0)
        ->and($plan->verdict())->toBe('reachable')
        ->and($plan->meetsTarget())->toBeTrue();
});

it('says so when the recording is already shorter than the target', function (): void {
    $plan = budgetPlanner()->plan(planTranscript(), 600_000, 660_000);

    expect($plan->requiredRemovalMs())->toBe(0)
        ->and($plan->verdict())->toBe('target_met')
        ->and($plan->shortfallMs())->toBe(0);
});

it('lists the longest gaps first and only the configured number of them', function (): void {
    $plan = new DurationBudgetPlanner(gapFloorMs: 250, longestGaps: 2)
        ->plan(planTranscript(), 600_000, null);

    expect(array_column($plan->longestGaps, 'gap_ms'))->toBe([1_500, 900]);
});

it('ignores bracketed sounds, which are not speech anyone can cut as a gap', function (): void {
    $transcript = new Transcript([
        new TranscriptWord('[laughs]', 0, 300),
        new TranscriptWord('sigo', 1_500, 1_800),
    ]);

    expect(budgetPlanner()->plan($transcript, 2_000, null)->gapCount)->toBe(0);
});

it('survives a round trip through the stored report payload', function (): void {
    $plan = budgetPlanner()->plan(planTranscript(), 600_000, 570_000, aiCuts: [
        ['startMs' => 0, 'endMs' => 30_000],
    ]);

    $restored = DurationPlan::fromArray($plan->toArray());

    expect($restored?->toArray())->toBe($plan->toArray());
});
