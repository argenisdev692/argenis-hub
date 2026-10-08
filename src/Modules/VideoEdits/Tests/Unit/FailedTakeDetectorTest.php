<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Services\FailedTakeDetector;
use Modules\VideoEdits\Domain\ValueObjects\FailedTakeAnchor;
use Modules\VideoEdits\Domain\ValueObjects\FailedTakeBoundary;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

function failedTakeDetector(): FailedTakeDetector
{
    return new FailedTakeDetector(boundaryGapMs: 400, maxLookbackMs: 8_000, minTakeMs: 400);
}

/**
 * @param  list<array{0: string, 1: int, 2: int}>  $rows
 * @return list<TranscriptWord>
 */
function takeWords(array $rows): array
{
    return array_map(
        static fn (array $row): TranscriptWord => new TranscriptWord($row[0], $row[1], $row[2]),
        $rows,
    );
}

/** The anchor a pause marker at $startMs would produce. */
function markerAt(int $startMs): FailedTakeAnchor
{
    return FailedTakeAnchor::pauseMarker($startMs);
}

it('proposes nothing without a marker or a transcript', function (): void {
    $words = takeWords([['Hola', 0, 400]]);

    expect(failedTakeDetector()->detect($words, []))->toBe([])
        ->and(failedTakeDetector()->detect([], [markerAt(0)]))->toBe([]);
});

it('takes the abandoned words back to the sentence that closed before them', function (): void {
    // "…del propio hilo. Vamos a compararlos y de- / pausa aca / Dentro del…"
    // The full stop on "hilo." is the boundary: the take cannot reach past it.
    $words = takeWords([
        ['del', 0, 200],
        ['propio', 210, 500],
        ['hilo.', 510, 900],
        ['Vamos', 2_000, 2_300],
        ['a', 2_310, 2_360],
        ['compararlos', 2_370, 3_000],
        ['y', 3_010, 3_080],
        ['de-', 3_090, 3_400],
        ['pausa', 3_900, 4_200],
        ['aca', 4_210, 4_500],
        ['Dentro', 4_600, 5_000],
    ]);

    $takes = failedTakeDetector()->detect($words, [markerAt(3_900)]);

    expect($takes)->toHaveCount(1)
        ->and($takes[0]->startMs)->toBe(2_000)
        ->and($takes[0]->endMs)->toBe(3_400)
        ->and($takes[0]->startWordIndex)->toBe(3)
        ->and($takes[0]->endWordIndex)->toBe(7)
        ->and($takes[0]->wordCount())->toBe(5)
        ->and($takes[0]->boundary)->toBe(FailedTakeBoundary::Sentence);
});

it('stops at a pause rather than swallowing the sentence before the take', function (): void {
    // The gap between "cerrado." and "Vamos" is 1.1 s, so the take starts at
    // "Vamos" whether or not the full stop was there.
    $words = takeWords([
        ['Ya', 0, 200],
        ['cerrado.', 210, 600],
        ['Vamos', 1_700, 2_100],
        ['a', 2_110, 2_160],
        ['repetirlo', 2_170, 2_900],
        ['pausa', 3_000, 3_300],
    ]);

    $takes = failedTakeDetector()->detect($words, [markerAt(3_000)]);

    expect($takes)->toHaveCount(1)
        ->and($takes[0]->startMs)->toBe(1_700)
        ->and($takes[0]->boundary)->toBe(FailedTakeBoundary::Sentence);
});

it('reports a gap as the boundary when the sentence ran on without punctuation', function (): void {
    $words = takeWords([
        ['Una', 0, 300],
        ['idea', 310, 700],
        ['larga', 710, 1_100],
        ['que', 1_110, 1_300],
        ['sigue', 2_000, 2_400],
        ['mal', 2_410, 2_800],
        ['pausa', 2_900, 3_200],
    ]);

    $takes = failedTakeDetector()->detect($words, [markerAt(2_900)]);

    expect($takes)->toHaveCount(1)
        ->and($takes[0]->startMs)->toBe(2_000)
        ->and($takes[0]->boundary)->toBe(FailedTakeBoundary::Gap);
});

it('stops at the look-back ceiling and admits the start is a guess', function (): void {
    $short = new FailedTakeDetector(boundaryGapMs: 400, maxLookbackMs: 900, minTakeMs: 400);

    $words = takeWords([
        ['Uno', 0, 400],
        ['dos', 420, 800],
        ['tres', 820, 1_200],
        ['cuatro', 1_220, 1_600],
        ['pausa', 1_700, 2_000],
    ]);

    $takes = $short->detect($words, [markerAt(1_700)]);

    // 900 ms back from the marker's first word reaches "tres", not "Uno".
    expect($takes)->toHaveCount(1)
        ->and($takes[0]->startMs)->toBe(820)
        ->and($takes[0]->boundary)->toBe(FailedTakeBoundary::LookbackLimit)
        // Never starts ticked: nothing proved where the take began.
        ->and($takes[0]->boundary->confidence())->toBeLessThan(0.85);
});

it('refuses a fragment too short to be a restarted sentence', function (): void {
    // The dangling conjunction is 50 ms of audio. CutEdgeTrimmer already absorbs
    // it into the marker cut, so proposing it as a take would only teach the
    // owner to approve boxes without reading them.
    $words = takeWords([
        ['y', 450, 500],
        ['pausa', 600, 900],
        ['aca', 910, 1_100],
    ]);

    expect(failedTakeDetector()->detect($words, [markerAt(600)]))->toBe([]);
});

it('proposes a take whose words alone clear the floor even when they end mid-thought', function (): void {
    // Two real words plus that conjunction is 900 ms — long enough that the owner
    // may well want it gone, so it is shown rather than hidden.
    $words = takeWords([
        ['Veralux', 0, 400],
        ['Group', 410, 800],
        ['y', 850, 900],
        ['pausa', 1_000, 1_300],
        ['aca', 1_310, 1_500],
    ]);

    $takes = failedTakeDetector()->detect($words, [markerAt(1_000)]);

    expect($takes)->toHaveCount(1)
        ->and($takes[0]->startMs)->toBe(0)
        ->and($takes[0]->durationMs())->toBe(900)
        ->and($takes[0]->wordCount())->toBe(3);
});

it('proposes nothing when the marker had nothing wrong before it', function (): void {
    $words = takeWords([
        ['Esta', 0, 400],
        ['frase', 410, 900],
        ['esta', 910, 1_300],
        ['bien.', 1_310, 1_700],
        ['pausa', 1_800, 2_100],
    ]);

    expect(failedTakeDetector()->detect($words, [markerAt(1_800)]))->toBe([]);
});

it('never reaches a take back across a marker already cut', function (): void {
    // Two markers 2 s apart: the second one's take must start after the first
    // one, never swallow it and everything before it.
    $words = takeWords([
        ['Primer', 0, 500],
        ['intento', 510, 1_000],
        ['pausa', 1_100, 1_400],
        ['aca', 1_410, 1_600],
        ['Segundo', 3_000, 3_500],
        ['intento', 3_510, 4_000],
        ['pausa', 4_100, 4_400],
        ['aca', 4_410, 4_600],
    ]);

    $takes = failedTakeDetector()->detect($words, [markerAt(1_100), markerAt(4_100)]);

    expect($takes)->toHaveCount(2)
        ->and($takes[0]->startMs)->toBe(0)
        ->and($takes[1]->startMs)->toBe(3_000);
});

it('ignores a marker it cannot locate in the transcript', function (): void {
    // Whisper timings are the join between the two, so a marker whose start does
    // not match any word is skipped rather than guessed at.
    $words = takeWords([
        ['Algo', 0, 400],
        ['pausa', 500, 800],
    ]);

    expect(failedTakeDetector()->detect($words, [markerAt(499)]))->toBe([]);
});
