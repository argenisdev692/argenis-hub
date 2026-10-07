<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\SpeechCategory;
use Modules\VideoEdits\Domain\Services\SpeechDisfluencyDetector;
use Modules\VideoEdits\Domain\ValueObjects\SpeechDetection;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

function detector(): SpeechDisfluencyDetector
{
    return new SpeechDisfluencyDetector(
        fillerSounds: ['eh', 'mmm', 'este'],
        fillerWords: ['bueno', 'entonces', 'like'],
        fillerPhrases: ['o sea', 'you know'],
        repetitionAllowList: ['no', 'muy'],
        maxStutterFragmentMs: 400,
        minConfidence: 0.5,
        pausePhrases: ['pausa', 'pausa aca', 'pausa aqui', 'otra vez'],
        elongationPatterns: [
            '/^([aeiou]|m|n|r|j|s|l|h)\1{2,}$/',
            '/([aeiou])\1{2,}$/',
        ],
    );
}

/**
 * Builds a transcript from `[text, startMs, endMs, confidence?]` tuples.
 *
 * @param  list<array{0: string, 1: int, 2: int, 3?: float}>  $words
 */
function transcriptOf(array $words): Transcript
{
    return new Transcript(array_map(
        static fn (array $word): TranscriptWord => new TranscriptWord(
            $word[0],
            $word[1],
            $word[2],
            $word[3] ?? null,
        ),
        $words,
    ));
}

/**
 * @param  list<SpeechDetection>  $detections
 * @return list<array{0: string, 1: int}>
 */
function detectedAs(array $detections): array
{
    return array_map(
        static fn (SpeechDetection $detection): array => [$detection->category->value, $detection->startMs],
        $detections,
    );
}

it('finds hesitation sounds and leaves real speech alone', function (): void {
    $transcript = transcriptOf([
        ['Hoy', 0, 300],
        ['eh', 300, 600],
        ['vamos', 600, 1_000],
        ['Mmm,', 1_000, 1_300],
        ['a', 1_300, 1_400],
        ['empezar', 1_400, 2_000],
    ]);

    $detections = detector()->detect($transcript, [SpeechCategory::Filler]);

    // "Mmm," matches despite the capital and the comma.
    expect(detectedAs($detections))->toBe([['filler', 300], ['filler', 1_000]]);
});

it('removes multi-word padding as one unit but never its words alone', function (): void {
    $transcript = transcriptOf([
        ['Esto', 0, 300],
        ['o', 300, 400],
        ['sea', 400, 600],
        ['funciona', 600, 1_200],
        // "sea" on its own is ordinary Spanish and must survive.
        ['sea', 1_200, 1_500],
        ['cual', 1_500, 1_800],
    ]);

    $detections = detector()->detect($transcript, [SpeechCategory::FillerWord]);

    expect($detections)->toHaveCount(1)
        ->and($detections[0]->startMs)->toBe(300)
        ->and($detections[0]->endMs)->toBe(600);
});

it('cuts the first of a repeated pair so the sentence continues cleanly', function (): void {
    $transcript = transcriptOf([
        ['el', 0, 200],
        ['el', 200, 400],
        ['problema', 400, 1_000],
    ]);

    $detections = detector()->detect($transcript, [SpeechCategory::Repetition]);

    // The SECOND "el" is the one the speaker carried on from.
    expect($detections)->toHaveCount(1)
        ->and($detections[0]->startMs)->toBe(0)
        ->and($detections[0]->endMs)->toBe(200);
});

it('treats a doubled emphasis word as speech, not a stumble', function (): void {
    $transcript = transcriptOf([
        ['no', 0, 200],
        ['no', 200, 400],
        ['funciona', 400, 1_000],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::Repetition]))->toBe([]);
});

it('finds a false start from its hyphen or its brevity', function (): void {
    $hyphenated = transcriptOf([
        ['pe-', 0, 500],
        ['pero', 500, 900],
    ]);
    $short = transcriptOf([
        ['pe', 0, 200],
        ['pero', 200, 600],
    ]);

    expect(detector()->detect($hyphenated, [SpeechCategory::Stutter]))->toHaveCount(1)
        ->and(detector()->detect($short, [SpeechCategory::Stutter]))->toHaveCount(1);
});

it('keeps a real word that merely begins the next one', function (): void {
    // "por" then "porque" is ordinary Spanish; a long fragment with no hyphen
    // is not evidence of a stumble.
    $transcript = transcriptOf([
        ['por', 0, 600],
        ['porque', 600, 1_200],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::Stutter]))->toBe([]);
});

it('finds the non-verbal audio Whisper brackets', function (): void {
    $transcript = transcriptOf([
        ['Hola', 0, 400],
        ['[laughs]', 400, 1_200],
        ['(coughs)', 1_200, 1_800],
        ['adios', 1_800, 2_200],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::VocalSound]))->toHaveCount(2);
});

it('never cuts on a word the provider was unsure about', function (): void {
    $transcript = transcriptOf([
        ['eh', 0, 300, 0.2],
        ['eh', 300, 600, 0.9],
    ]);

    $detections = detector()->detect($transcript, [SpeechCategory::Filler]);

    expect($detections)->toHaveCount(1)
        ->and($detections[0]->startMs)->toBe(300);
});

it('produces only the categories that were asked for', function (): void {
    $transcript = transcriptOf([
        ['eh', 0, 300],
        ['bueno', 300, 700],
        ['el', 700, 900],
        ['el', 900, 1_100],
        ['[laughs]', 1_100, 1_600],
    ]);

    $onlyFillers = detector()->detect($transcript, [SpeechCategory::Filler]);
    $everything = detector()->detect($transcript, SpeechCategory::all());

    expect($onlyFillers)->toHaveCount(1)
        ->and(count($everything))->toBeGreaterThan(count($onlyFillers));
});

it('returns detections in playback order whatever the category', function (): void {
    $transcript = transcriptOf([
        ['[laughs]', 2_000, 2_500],
        ['eh', 0, 300],
        ['bueno', 800, 1_200],
    ]);

    $starts = array_map(
        static fn (SpeechDetection $detection): int => $detection->startMs,
        detector()->detect($transcript, SpeechCategory::all()),
    );

    expect($starts)->toBe([0, 800, 2_000]);
});

it('detects nothing without a transcript or without categories', function (): void {
    expect(detector()->detect(new Transcript([]), SpeechCategory::all()))->toBe([])
        ->and(detector()->detect(transcriptOf([['eh', 0, 300]]), []))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Spoken pause markers (P1)
|--------------------------------------------------------------------------
|
| The reference recording flagged eight of these out loud — "Contestamos…
| pausa acá…", "pausa acá… Lo que marca la diferencia" — and every one of them
| had exactly one possible route to being cut: a Flash-tier model spotting a
| literal token in a two-thousand-word indexed list. They are a dictionary
| match, so they are a dictionary now.
|
*/

it('finds a spoken pause marker as one unit and not as its own words', function (): void {
    $transcript = transcriptOf([
        ['Contestamos', 0, 900],
        ['pausa', 900, 1_400],
        ['acá', 1_400, 1_800],
        ['y', 1_800, 1_900],
        ['empieza', 1_900, 2_600],
    ]);

    $detections = detector()->detect($transcript, [SpeechCategory::PauseMarker]);

    expect($detections)->toHaveCount(1)
        ->and($detections[0]->startMs)->toBe(900)
        ->and($detections[0]->endMs)->toBe(1_800);
});

it('finds every spelling of the marker accent and without', function (): void {
    $transcript = transcriptOf([
        ['Pausa.', 0, 500],
        ['PAUSA', 500, 1_000],
        ['pausa', 1_000, 1_500],
        ['aquí', 1_500, 2_000],
        ['otra', 2_000, 2_400],
        ['vez', 2_400, 2_800],
    ]);

    // Four markers, and "aquí"/"otra"/"vez" are never reported on their own —
    // a single-word "pausa" starting one word later is a separate marker, not
    // part of the one before it.
    expect(detectedAs(detector()->detect($transcript, [SpeechCategory::PauseMarker])))
        ->toBe([
            ['pause_marker', 0],
            ['pause_marker', 500],
            ['pause_marker', 1_000],
            ['pause_marker', 2_000],
        ]);
});

it('never treats ordinary Spanish as a pause marker', function (): void {
    // Every word here is a real word that could appear next to a marker in a
    // normal sentence. "para" and "otra" were both dictionary candidates and
    // both were rejected for exactly this reason.
    $transcript = transcriptOf([
        ['para', 0, 300],
        ['que', 300, 600],
        ['otra', 600, 900],
        ['cosa', 900, 1_300],
        ['sea', 1_300, 1_600],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::PauseMarker]))->toBe([]);
});

it('is a category of its own so a user can switch the markers off alone', function (): void {
    $transcript = transcriptOf([
        ['Contestamos', 0, 900],
        ['pausa', 900, 1_300],
        ['acá', 1_300, 1_700],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::Filler]))->toBe([])
        ->and(detector()->detect($transcript, SpeechCategory::all()))->toHaveCount(1);
});

it('persists the marker under a cut reason a cut may be proposed for', function (): void {
    expect(SpeechCategory::PauseMarker->toCutReason())->toBe(CutReason::PauseMarker)
        ->and(SpeechCategory::PauseMarker->value)->toBe('pause_marker')
        ->and(SpeechCategory::values())->toContain('pause_marker');
});

/*
|--------------------------------------------------------------------------
| Vocal elongation (P3)
|--------------------------------------------------------------------------
|
| "eeee", "y deee", "para queee" from the same recording. Whisper spells the
| same hesitation differently every time, so this can only ever be a pattern.
|
*/

it('finds a held vowel however many times it is spelled', function (): void {
    $transcript = transcriptOf([
        ['eee', 0, 600],
        ['eeee', 600, 1_400],
        ['eeeee', 1_400, 2_400],
        ['mmm', 2_400, 3_000],
    ]);

    expect(detectedAs(detector()->detect($transcript, [SpeechCategory::Filler])))
        ->toBe([['filler', 0], ['filler', 600], ['filler', 1_400], ['filler', 2_400]]);
});

it('finds an elongated vowel inside a real word', function (): void {
    $transcript = transcriptOf([
        ['y', 0, 200],
        ['deee', 200, 900],
        ['para', 900, 1_300],
        ['queee', 1_300, 2_100],
    ]);

    // Only the held words; "y" and "para" are ordinary speech.
    expect(detectedAs(detector()->detect($transcript, [SpeechCategory::Filler])))
        ->toBe([['filler', 200], ['filler', 1_300]]);
});

it('leaves a real word that merely ends in a doubled vowel', function (): void {
    // Two repeats is an English diphthong, not a held vowel: the 3-repeat floor
    // is the whole safety rule, and dropping it would start deleting words on a
    // recording that switches language.
    $transcript = transcriptOf([
        ['see', 0, 400],
        ['bee', 400, 800],
        ['coffee', 800, 1_400],
        ['three', 1_400, 2_000],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::Filler]))->toBe([]);
});

it('leaves an ordinary Spanish word that a loose pattern would have matched', function (): void {
    // "ana" is three letters from the hesitation character set, but they are not
    // the same letter repeated — which is why the bare-run pattern uses a
    // backreference instead of a character class.
    $transcript = transcriptOf([
        ['ana', 0, 500],
        ['era', 500, 900],
        ['algo', 900, 1_400],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::Filler]))->toBe([]);
});

it('reads an elongation as a hesitation, not as a vocal sound', function (): void {
    $transcript = transcriptOf([
        ['eeee', 0, 800],
        ['[laughs]', 800, 1_400],
    ]);

    // They are separate categories, and a user switching one off must not lose
    // the other.
    expect(detectedAs(detector()->detect($transcript, [SpeechCategory::Filler])))
        ->toBe([['filler', 0]])
        ->and(detectedAs(detector()->detect($transcript, [SpeechCategory::VocalSound])))
        ->toBe([['vocal_sound', 800]]);
});
