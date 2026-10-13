<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Services\CutSeamTrimmer;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

function seamTrimmer(int $absorbMs = 600, int $breathMs = 250): CutSeamTrimmer
{
    return new CutSeamTrimmer($absorbMs, $breathMs);
}

it('closes the hole a removed phrase leaves behind', function (): void {
    // "vamos a [ESTA FRASE MAL] ingresar este prompt" — 480 ms of air before the
    // removed words and 900 ms after. Welding the two kept fragments untouched
    // gives a 1.38 s hole mid-sentence, which is the reported
    // "vamos… a … ingresar este prompt".
    $words = [
        new TranscriptWord('vamos', 0, 400),
        new TranscriptWord('a', 450, 520),
        new TranscriptWord('esta', 1_000, 1_200),
        new TranscriptWord('frase', 1_250, 1_450),
        new TranscriptWord('mal', 1_500, 1_700),
        new TranscriptWord('ingresar', 2_600, 3_200),
    ];

    // 1_380 ms available, 250 ms guaranteed, 600 ms cap → 600 ms removed, split
    // 209/391 by the air each side actually had.
    expect(seamTrimmer()->absorbFlankingSilence(1_000, 1_700, $words))->toBe([791, 2_091]);
});

it('leaves a smaller hole when the absorb cap is the binding limit', function (): void {
    // A long pause before the cut and a longer one after: 3_000 ms of air, of
    // which the breath would allow 2_750 ms to go but only 600 ms are authorised.
    $words = [
        new TranscriptWord('antes', 0, 400),
        new TranscriptWord('despues', 4_000, 4_600),
    ];

    expect(seamTrimmer()->absorbFlankingSilence(1_300, 1_900, $words))->toBe([1_120, 2_320]);
});

it('splits the breath across both sides in proportion to the air each had', function (): void {
    // A lopsided seam: a long pause before the cut, a short one after. Giving
    // each side the full 250 ms would leave 500 ms and read as uneven, so the
    // breath is the TOTAL and is shared in proportion instead.
    $words = [
        new TranscriptWord('antes', 0, 300),
        new TranscriptWord('despues', 2_500, 3_000),
    ];

    // 1_600 before, 600 after. 600 ms authorised → 436 taken from the long side
    // and 164 from the short one.
    $trimmed = seamTrimmer()->absorbFlankingSilence(1_900, 1_900, $words);

    expect($trimmed)->toBe([1_464, 2_064]);
});

it('does nothing when the seam already sits inside the breath', function (): void {
    // 40 ms of air on either side. Expanding would mean eating the words.
    $words = [
        new TranscriptWord('antes', 0, 1_000),
        new TranscriptWord('despues', 1_040, 2_000),
    ];

    expect(seamTrimmer()->absorbFlankingSilence(1_000, 1_040, $words))->toBe([1_000, 1_040]);
});

it('never expands past a neighbouring word however generous the budget', function (): void {
    // 100 ms before, 2_000 ms after. A budget big enough to want all of the long
    // side — and it still cannot reach the word, because a share is always a
    // fraction of the silence that side actually had.
    $words = [
        new TranscriptWord('antes', 0, 500),
        new TranscriptWord('despues', 2_600, 3_200),
    ];

    $trimmed = seamTrimmer(absorbMs: 10_000)->absorbFlankingSilence(600, 600, $words);

    expect($trimmed[0])->toBeGreaterThanOrEqual(500)
        ->and($trimmed[1])->toBeLessThanOrEqual(2_600);
});

it('does nothing at the very start of a recording', function (): void {
    // No previous word, so there is nothing to measure the leading air against.
    $words = [new TranscriptWord('ingresar', 3_000, 3_500)];

    expect(seamTrimmer()->absorbFlankingSilence(0, 3_000, $words))->toBe([0, 3_000]);
});

it('does nothing at the very end of a recording', function (): void {
    $words = [new TranscriptWord('vamos', 0, 400)];

    expect(seamTrimmer()->absorbFlankingSilence(400, 10_000, $words))->toBe([400, 10_000]);
});

it('is inert when either budget is non-positive', function (): void {
    $words = [
        new TranscriptWord('antes', 0, 400),
        new TranscriptWord('despues', 4_000, 4_600),
    ];

    // Both are treated as "off" here. A zero breath is not a legitimate way to
    // ask for no seam margin — it would mean welding the two flanks together,
    // which is the hole — so the service refuses it rather than honouring it,
    // and the container floors the value instead.
    expect(seamTrimmer(absorbMs: 0)->absorbFlankingSilence(1_300, 1_900, $words))->toBe([1_300, 1_900])
        ->and(seamTrimmer(breathMs: 0)->absorbFlankingSilence(1_300, 1_900, $words))->toBe([1_300, 1_900]);
});

it('handles a transcript with no words at all', function (): void {
    expect(seamTrimmer()->absorbFlankingSilence(1_000, 2_000, []))->toBe([1_000, 2_000]);
});
