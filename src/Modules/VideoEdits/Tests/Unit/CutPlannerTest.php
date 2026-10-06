<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionRejectionReason;
use Modules\VideoEdits\Domain\Exceptions\InvalidCutRangesException;
use Modules\VideoEdits\Domain\Services\CutPlanner;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\TimeRange;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptSegment;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;
use Modules\VideoEdits\Domain\ValueObjects\ValidatedDecision;
use Modules\VideoEdits\Domain\ValueObjects\WaitCompressionPolicy;

function planner(): CutPlanner
{
    return new CutPlanner(
        silencePaddingAfterSpeechMs: 250,
        silencePaddingBeforeSpeechMs: 100,
        wordGuardAfterMs: 150,
        wordGuardBeforeMs: 60,
        minKeptFragmentMs: 250,
        minOutputMs: 1_000,
    );
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

it('keeps a wider speech margin after speech than before it', function (): void {
    $plan = planner()->plan([silence(10_000, 12_000)], 60_000);

    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[10_250, 11_900]])
        ->and(bounds($plan->keepRanges))->toBe([[0, 10_250], [11_900, 60_000]])
        ->and($plan->removedDurationMs())->toBe(1_650);
});

it('never starts a silence cut inside the tail of a transcribed word', function (): void {
    // ffmpeg hears silence from 10 000, but Whisper says "compartir" ends at 10 300.
    $plan = planner()->plan(
        [silence(10_000, 12_000)],
        60_000,
        [new TranscriptWord('compartir', 9_600, 10_300), new TranscriptWord('ahora', 12_050, 12_400)],
    );

    // Start: word end 10 300 + 150 guard, later than the 250 ms padding. End: the 100 ms padding stays tighter than the next word's guard.
    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[10_450, 11_900]]);
});

it('pulls the end of a silence cut back before an early word attack', function (): void {
    $plan = planner()->plan([silence(10_000, 12_000)], 60_000, [new TranscriptWord('herramienta', 11_850, 12_500)]);

    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[10_250, 11_790]]);
});

it('splits a silence around a quiet word ffmpeg missed', function (): void {
    $plan = planner()->plan([silence(10_000, 14_000)], 60_000, [new TranscriptWord('y', 11_900, 12_100)]);

    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[10_250, 11_840], [12_250, 13_900]])
        ->and($plan->decisions[0]->isApplied())->toBeTrue();
});

it('rejects a silence that lies entirely inside speech', function (): void {
    $plan = planner()->plan([silence(10_000, 11_000)], 60_000, [new TranscriptWord('editar', 10_100, 10_900)]);

    expect($plan->hasCuts())->toBeFalse()
        ->and($plan->decisions[0]->rejectionReason)->toBe(DecisionRejectionReason::OverlapsSpeech);
});

function busySilence(int $startMs, int $endMs, float $screenActivity): ValidatedDecision
{
    return ValidatedDecision::applied(new CutDecision(
        'silence_detector', CutReason::Silence, DecisionOrigin::SystemDetection, $startMs, $endMs,
        evidence: ['threshold_ms' => 1_000, 'screen_activity' => $screenActivity],
    ));
}

function politePlanner(int $breathMinGapMs = 0, int $midSentencePauseMs = 0): CutPlanner
{
    return new CutPlanner(
        silencePaddingAfterSpeechMs: 250,
        silencePaddingBeforeSpeechMs: 100,
        wordGuardAfterMs: 150,
        wordGuardBeforeMs: 60,
        minKeptFragmentMs: 250,
        minOutputMs: 1_000,
        waitCompression: new WaitCompressionPolicy(true, 0.5, 1_500, 1_000, 3.0, 4_000),
        breathMinGapMs: $breathMinGapMs,
        midSentencePauseMs: $midSentencePauseMs,
    );
}

it('speeds up a silence with a busy screen instead of cutting it', function (): void {
    // Padded wait 10 250 – 21 900: 1.5 s kept at the start, 1 s at the end,
    // the 9 150 ms middle played at 3× (3 050 ms on screen).
    $plan = politePlanner()->plan([busySilence(10_000, 22_000, 0.9)], 60_000);

    expect($plan->hasCuts())->toBeFalse()
        ->and($plan->compressedRanges)->toHaveCount(1)
        ->and(bounds([$plan->compressedRanges[0]->range]))->toBe([[11_750, 20_900]])
        ->and($plan->compressedRanges[0]->speed)->toBe(3.0)
        ->and($plan->finalDurationMs)->toBe(60_000 - (9_150 - 3_050))
        ->and($plan->decisions[0]->rejectionReason)->toBe(DecisionRejectionReason::ScreenActivity)
        ->and($plan->rejectedDecisionCount())->toBe(0);
});

it('caps how long a very long wait lasts on screen', function (): void {
    $plan = politePlanner()->plan([busySilence(0, 60_000, 1.0)], 120_000);

    // The 57 150 ms middle would last 19 s at 3×; capped to 4 s on screen instead.
    expect($plan->compressedRanges[0]->outputDurationMs())->toBeLessThanOrEqual(4_000)
        ->and($plan->compressedRanges[0]->speed)->toBeGreaterThan(3.0);
});

it('keeps a short tool wait exactly as recorded', function (): void {
    $plan = politePlanner()->plan([busySilence(10_000, 13_000, 0.9)], 60_000);

    expect($plan->hasCuts())->toBeFalse()
        ->and($plan->compressedRanges)->toBe([])
        ->and($plan->finalDurationMs)->toBe(60_000);
});

it('still cuts a silence where the screen stood still', function (): void {
    $plan = politePlanner()->plan([busySilence(10_000, 12_000, 0.2)], 60_000);

    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[10_250, 11_900]])
        ->and($plan->compressedRanges)->toBe([]);
});

function speechCut(CutReason $reason, int $startMs, int $endMs): ValidatedDecision
{
    return ValidatedDecision::applied(new CutDecision('speech_detector', $reason, DecisionOrigin::SystemDetection, $startMs, $endMs));
}

function aiCut(int $startMs, int $endMs): ValidatedDecision
{
    return ValidatedDecision::applied(new CutDecision('ai_edit', CutReason::PauseMarker, DecisionOrigin::Ai, $startMs, $endMs));
}

it('refuses a stutter cut that falls inside a tool wait', function (): void {
    // Claude is streaming its answer from 10 250 to 21 900; the speaker stutters
    // in the middle of it. Splicing there is the abrupt cut nobody wants.
    $plan = politePlanner()->plan([
        busySilence(10_000, 22_000, 0.9),
        speechCut(CutReason::Stutter, 14_000, 14_200),
    ], 60_000);

    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([])
        ->and($plan->decisions[1]->rejectionReason)->toBe(DecisionRejectionReason::ScreenBusy)
        ->and($plan->decisions[1]->isApplied())->toBeFalse()
        // The wait itself is still honoured — it is compressed, not lost.
        ->and($plan->compressedRanges)->not->toBe([]);
});

it('refuses an AI pause-marker cut that falls inside a tool wait', function (): void {
    $plan = politePlanner()->plan([
        busySilence(10_000, 22_000, 0.9),
        aiCut(15_000, 15_400),
    ], 60_000);

    expect($plan->hasCuts())->toBeFalse()
        ->and($plan->decisions[1]->rejectionReason)->toBe(DecisionRejectionReason::ScreenBusy);
});

it('trims a cut back to the edge of a tool wait instead of dropping it', function (): void {
    $plan = politePlanner()->plan([
        busySilence(10_000, 22_000, 0.9),
        speechCut(CutReason::Filler, 21_700, 22_500),
    ], 60_000);

    // The wait ends at 21 900; the part after it is a real filler and survives.
    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[21_900, 22_500]])
        ->and($plan->decisions[1]->rejectionReason)->toBeNull();
});

it('still applies a manual range inside a tool wait', function (): void {
    $plan = politePlanner()->plan([
        busySilence(10_000, 22_000, 0.9),
        manualCut(14_000, 14_200),
    ], 60_000);

    // The owner looked at that range and asked for it, which outranks the default.
    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[14_000, 14_200]])
        ->and($plan->decisions[1]->rejectionReason)->toBeNull();
});

it('leaves cuts alone when the screen was still for the whole silence', function (): void {
    $plan = politePlanner()->plan([
        busySilence(10_000, 12_000, 0.2),
        speechCut(CutReason::Stutter, 10_300, 10_500),
    ], 60_000);

    // Still screen → the silence is cut, and the stutter inside it merges as usual.
    expect($plan->compressedRanges)->toBe([])
        ->and(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[10_250, 11_900]])
        ->and($plan->decisions[1]->rejectionReason)->toBeNull();
});

it('keeps some air when a pause is cut in the middle of a sentence', function (): void {
    $segments = [
        new TranscriptSegment('Vamos a abrir el panel y', 8_000, 10_000),
        new TranscriptSegment('Eso es todo.', 20_000, 22_000),
    ];

    $plan = politePlanner(midSentencePauseMs: 300)->plan(
        [silence(10_000, 12_000), silence(22_000, 24_000)],
        60_000,
        [],
        $segments,
    );

    // Mid-sentence: 300 ms kept after speech. After "todo.": the usual 250 ms.
    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[10_300, 11_900], [22_250, 23_900]]);
});

it('turns down the breaths between words without cutting them', function (): void {
    $words = [
        new TranscriptWord('editar', 1_000, 1_500),
        new TranscriptWord('y', 2_200, 2_300),       // 700 ms gap → breath
        new TranscriptWord('compartir', 2_400, 3_000), // 100 ms gap → articulation, left alone
    ];

    $plan = politePlanner(breathMinGapMs: 250)->plan([], 60_000, $words);

    expect($plan->hasCuts())->toBeFalse()
        ->and(bounds($plan->attenuatedRanges))->toBe([[1_650, 2_140]])
        ->and($plan->finalDurationMs)->toBe(60_000);
});

it('never turns down a breath that a cut already removes', function (): void {
    $words = [new TranscriptWord('uno', 1_000, 1_500), new TranscriptWord('dos', 5_000, 5_400)];

    $plan = politePlanner(breathMinGapMs: 250)->plan([silence(1_500, 5_000)], 60_000, $words);

    // Cut 1 750 – 4 900 (after the word guards). What is left of the gap either
    // side is shorter than a breath worth ramping, so nothing is attenuated.
    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[1_750, 4_900]])
        ->and($plan->attenuatedRanges)->toBe([]);
});

it('never keeps manual cuts off words', function (): void {
    $plan = planner()->plan([manualCut(10_000, 12_000)], 60_000, [new TranscriptWord('eh', 10_000, 10_300)]);

    expect(bounds(array_map(static fn ($cut) => $cut->range, $plan->appliedCuts)))->toBe([[10_000, 12_000]]);
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
        silence(10_000, 13_000),      // → 10 250 – 12 900
        manualCut(12_500, 15_000),    // overlaps the padded silence
        manualCut(15_000, 16_000),    // touches the previous cut
    ], 60_000);

    expect($plan->appliedCuts)->toHaveCount(1)
        ->and(bounds([$plan->appliedCuts[0]->range]))->toBe([[10_250, 16_000]])
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
    $plan = planner()->plan([manualCut(0, 59_500)], 60_000);
})->throws(InvalidCutRangesException::class);

it('reports the output problem as user-safe details', function (): void {
    try {
        $plan = planner()->plan([manualCut(0, 59_500)], 60_000);
    } catch (InvalidCutRangesException $exception) {
        // The 500 ms tail is longer than the 250 ms fragment floor, so it is kept — and still too short.
        expect($exception->details)->toBe(['output' => ['final_duration_ms' => 500, 'minimum_duration_ms' => 1_000]]);

        return;
    }

    throw new RuntimeException('Expected InvalidCutRangesException.');
});
