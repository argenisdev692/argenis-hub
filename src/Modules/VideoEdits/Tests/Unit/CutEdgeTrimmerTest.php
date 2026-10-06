<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Services\CutEdgeTrimmer;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

function trimmer(): CutEdgeTrimmer
{
    return new CutEdgeTrimmer(400, ['y', 'e', 'o', 'u', 'de', 'la', 'el', 'lo', 'que', 'para']);
}

it('takes the trailing conjunction with the cut', function (): void {
    // "Vamos a entrar al project de Veralux Group y…" then the cut, then "Cuatro casos…"
    $words = [
        new TranscriptWord('Grupo', 1_000, 1_300),
        new TranscriptWord('y', 1_350, 1_420),
        new TranscriptWord('Cuatro', 2_000, 2_400),
    ];

    expect(trimmer()->absorbDanglingEdges(1_600, 2_000, $words))->toBe([1_350, 2_000]);
});

it('takes the half word the transcriber heard cut off', function (): void {
    // "…se van a reutilizar o co-" — the hyphen is Whisper saying it was clipped.
    $words = [
        new TranscriptWord('reutilizar', 500, 900),
        new TranscriptWord('o', 950, 1_000),
        new TranscriptWord('co-', 1_010, 1_100),
        new TranscriptWord('Para', 1_900, 2_100),
    ];

    expect(trimmer()->absorbDanglingEdges(1_300, 1_600, $words))->toBe([950, 1_600]);
});

it('walks back over a whole chain of function words', function (): void {
    $words = [
        new TranscriptWord('de', 700, 760),
        new TranscriptWord('la', 770, 830),
        new TranscriptWord('el', 840, 880),
    ];

    expect(trimmer()->absorbDanglingEdges(1_000, 2_000, $words))->toBe([700, 2_000]);
});

it('stops at the first word that may legally end a sentence', function (): void {
    // "para el día a día y…" — "día" can end a sentence, so only "y" goes.
    $words = [
        new TranscriptWord('día', 1_100, 1_300),
        new TranscriptWord('y', 1_400, 1_480),
        new TranscriptWord('Cuatro', 2_000, 2_400),
    ];

    expect(trimmer()->absorbDanglingEdges(1_800, 2_000, $words))->toBe([1_400, 2_000]);
});

it('leaves the range alone when the neighbour is a real word', function (): void {
    $words = [
        new TranscriptWord('reutilizar', 1_000, 1_400),
        new TranscriptWord('Para', 2_000, 2_300),
    ];

    expect(trimmer()->absorbDanglingEdges(1_600, 2_000, $words))->toBe([1_600, 2_000]);
});

it('never grows a cut further than the absorb budget', function (): void {
    $words = [
        new TranscriptWord('y', 100, 160),
        new TranscriptWord('y', 200, 260),
        new TranscriptWord('y', 300, 360),
        new TranscriptWord('y', 400, 460),
        new TranscriptWord('y', 500, 560),
    ];

    // Starting at 700 with a 400 ms budget reaches back no further than 300.
    expect(trimmer()->absorbDanglingEdges(700, 800, $words))->toBe([300, 800]);
});

it('shares one budget between both edges', function (): void {
    $words = [
        new TranscriptWord('y', 500, 560),
        new TranscriptWord('co-', 900, 960),
    ];

    // 700 → 500 back is 200 ms of the 400 budget, leaving 200 for the front.
    expect(trimmer()->absorbDanglingEdges(700, 800, $words))->toBe([500, 960]);
});

it('keeps a function word that begins the next fragment', function (): void {
    // The front of a fragment is where a sentence legitimately starts, so a
    // leading "Para" is content rather than a seam leftover.
    $words = [
        new TranscriptWord('Para', 2_000, 2_300),
    ];

    expect(trimmer()->absorbDanglingEdges(1_600, 2_000, $words))->toBe([1_600, 2_000]);
});

it('does nothing at all with no transcript', function (): void {
    expect(trimmer()->absorbDanglingEdges(1_600, 2_000, []))->toBe([1_600, 2_000]);
});

it('never absorbs a bracketed non-verbal sound', function (): void {
    $words = [
        new TranscriptWord('[laughs]', 1_450, 1_520),
        new TranscriptWord('Cuatro', 2_000, 2_400),
    ];

    // The laughter stays: it is not a seam leftover, it is content.
    expect(trimmer()->absorbDanglingEdges(1_600, 2_000, $words))->toBe([1_600, 2_000]);
});
