<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Services\KeepPhraseGuard;
use Modules\VideoEdits\Domain\ValueObjects\AiCutProposal;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * "Hoy muy bien empezamos. Este paso es esteban."
 *
 * Indices: 0 Hoy · 1 muy · 2 bien · 3 empezamos · 4 Este · 5 paso · 6 es · 7 esteban
 */
function guardTranscript(): Transcript
{
    $words = [
        ['Hoy', 0, 200], ['muy', 210, 400], ['bien', 410, 700], ['empezamos', 710, 1_200],
        ['Este', 1_300, 1_500], ['paso', 1_510, 1_800], ['es', 1_810, 1_950], ['esteban', 1_960, 2_400],
    ];

    return new Transcript(array_map(
        static fn (array $word): TranscriptWord => new TranscriptWord($word[0], $word[1], $word[2]),
        $words,
    ));
}

function guardProposal(int $start, int $end): AiCutProposal
{
    return new AiCutProposal(CutReason::Retake, $start, $end, 0.9, 'Retake.');
}

it('keeps every proposal when no phrase is protected', function (): void {
    [$remaining, $blocked] = (new KeepPhraseGuard)->filter(
        [guardProposal(0, 1), guardProposal(4, 5)],
        guardTranscript(),
        [],
    );

    expect($remaining)->toHaveCount(2)
        ->and($blocked)->toBe([]);
});

it('drops a proposal that would delete a protected phrase', function (): void {
    [$remaining, $blocked] = (new KeepPhraseGuard)->filter(
        [guardProposal(1, 2), guardProposal(4, 5)],
        guardTranscript(),
        ['muy bien'],
    );

    expect($remaining)->toHaveCount(1)
        ->and($remaining[0]->startWordIndex)->toBe(4)
        ->and($blocked)->toBe(['muy bien']);
});

it('matches regardless of case, accents and punctuation', function (): void {
    [$remaining, $blocked] = (new KeepPhraseGuard)->filter(
        [guardProposal(1, 2)],
        guardTranscript(),
        ['Muy Bien.'],
    );

    expect($remaining)->toBe([])
        ->and($blocked)->toBe(['Muy Bien.']);
});

it('does not match inside a longer word', function (): void {
    // "este" must not protect "esteban", or every filler dictionary entry
    // would defend an entire family of words.
    [$remaining] = (new KeepPhraseGuard)->filter(
        [guardProposal(7, 7)],
        guardTranscript(),
        ['este'],
    );

    expect($remaining)->toHaveCount(1);
});

it('ignores phrases that normalise to nothing', function (): void {
    [$remaining, $blocked] = (new KeepPhraseGuard)->filter(
        [guardProposal(0, 3)],
        guardTranscript(),
        ['   ', '...'],
    );

    expect($remaining)->toHaveCount(1)
        ->and($blocked)->toBe([]);
});

it('reports each blocking phrase once, however many proposals it stopped', function (): void {
    [, $blocked] = (new KeepPhraseGuard)->filter(
        [guardProposal(1, 2), guardProposal(0, 3)],
        guardTranscript(),
        ['muy bien'],
    );

    expect($blocked)->toBe(['muy bien']);
});

it('drops an automatic decision whose range covers a protected phrase', function (): void {
    // "muy bien" said twice is a repetition to the detector and a phrase the
    // lesson needs to the owner; the guard is the only thing that knows both.
    $decisions = [
        new CutDecision('speech_detector', CutReason::Repetition, DecisionOrigin::Transcription, 210, 700, 1.0),
        new CutDecision('speech_detector', CutReason::Filler, DecisionOrigin::Transcription, 1_300, 1_500, 1.0),
    ];

    [$remaining, $blocked] = (new KeepPhraseGuard)->filterDecisions($decisions, guardTranscript(), ['muy bien']);

    expect($remaining)->toHaveCount(1)
        ->and($remaining[0]->reason)->toBe(CutReason::Filler)
        ->and($blocked)->toBe(['muy bien']);
});
