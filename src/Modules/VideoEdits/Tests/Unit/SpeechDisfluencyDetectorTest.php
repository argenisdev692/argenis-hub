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
    // The words are fed out of order and are spaced closely enough that the
    // robotic-gap rule has nothing to say — this test is about the sort, not
    // about how many categories a gap can trigger.
    $transcript = transcriptOf([
        ['[laughs]', 2_000, 2_500],
        ['eh', 0, 300],
        ['bueno', 320, 720],
    ]);

    $starts = array_map(
        static fn (SpeechDetection $detection): int => $detection->startMs,
        detector()->detect($transcript, SpeechCategory::all()),
    );

    expect($starts)->toBe([0, 320, 2_000]);
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

it('widens a point-aligned pause marker back over the audio it names', function (): void {
    // This is Whisper's own shape: "Pausa" at 1 500 ms with no duration and
    // "acá" carrying the span. Cutting from 1 500 leaves the spoken "pausa"
    // before it in the file, which is why some markers survived an edit.
    $transcript = transcriptOf([
        ['decidir', 0, 1_000],
        ['Pausa', 1_500, 1_500],
        ['acá', 1_500, 1_800],
        ['Clau', 1_900, 2_400],
    ]);

    $detections = detector()->detect($transcript, [SpeechCategory::PauseMarker]);

    expect($detections)->toHaveCount(1)
        ->and($detections[0]->startMs)->toBe(1_000)
        ->and($detections[0]->endMs)->toBe(1_800);
});

it('never widens a point marker into the word that follows it', function (): void {
    // No room before the point and no "acá" to carry a span: there is nothing
    // to remove, and cutting forward would take the next word's attack.
    $transcript = transcriptOf([
        ['decidir', 0, 1_500],
        ['Pausa', 1_500, 1_500],
        ['Clau', 1_500, 2_000],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::PauseMarker]))->toBe([]);
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

/*
|--------------------------------------------------------------------------
| Robotic intra-phrase gaps (P4)
|--------------------------------------------------------------------------
|
| "vamos… a… ingresar" — the speaker is not hesitating, the words are just
| spaced out enough to sound like a machine reading them. No other category
| covered this: fillers need a dictionary hit, stutters need a truncated word,
| and silence removal only ever looked at gaps of a second or more, so a 400 ms
| gap inside a phrase survived every rule the module had.
|
| The detector keeps `target_gap_ms` of real air and lets the renderer's join
| air make up the rest, which is why the composition root passes 150 - 80.
|
*/

it('closes an intra-phrase gap down to the target on both sides', function (): void {
    // 400 ms of air between two ordinary words of one phrase.
    $transcript = transcriptOf([
        ['vamos', 0, 500],
        ['a', 900, 1_000],
    ]);

    $detections = detector()->detect($transcript, [SpeechCategory::RoboticPause]);

    expect($detections)->toHaveCount(1)
        // 75 ms kept after "vamos", 75 ms kept before "a".
        ->and($detections[0]->startMs)->toBe(575)
        ->and($detections[0]->endMs)->toBe(825)
        ->and($detections[0]->evidence)->toMatchArray([
            'detector' => 'robotic_pause',
            'gap_ms' => 400,
            'target_gap_ms' => 150,
        ]);
});

it('keeps only what the renderer cannot add by itself', function (): void {
    // The real wiring: 150 ms total budget, 80 ms of join air, so the detector
    // may keep 70 ms of the recording's own air and nothing more.
    $detector = new SpeechDisfluencyDetector(
        fillerSounds: [],
        fillerWords: [],
        fillerPhrases: [],
        repetitionAllowList: [],
        maxStutterFragmentMs: 400,
        minConfidence: 0.5,
        roboticPauseTargetMs: 70,
    );

    $detections = $detector->detect(
        transcriptOf([['vamos', 0, 500], ['a', 900, 1_000]]),
        [SpeechCategory::RoboticPause],
    );

    expect($detections)->toHaveCount(1)
        ->and($detections[0]->startMs)->toBe(535)
        ->and($detections[0]->endMs)->toBe(865);
});

it('treats a gap already at the target as speech', function (): void {
    // Exactly 150 ms is the goal, not a problem: cutting it would remove air the
    // result is supposed to have.
    $transcript = transcriptOf([
        ['hola', 0, 500],
        ['que', 650, 900],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::RoboticPause]))->toBe([]);
});

it('closes a phrase the transcriber split with ellipses', function (): void {
    // The whole point: "vamos..." followed by a pause is still one phrase. A
    // terminal-punctuation rule that also fired on "..." would leave exactly the
    // delivery this category exists to repair.
    $transcript = transcriptOf([
        ['vamos...', 0, 500],
        ['a', 900, 1_000],
        ['ingresar...', 1_100, 1_600],
        ['ahora', 2_000, 2_400],
    ]);

    // Two 400 ms gaps; the 100 ms one between "a" and "ingresar" is left alone.
    expect(detectedAs(detector()->detect($transcript, [SpeechCategory::RoboticPause])))
        ->toBe([['robotic_pause', 575], ['robotic_pause', 1_675]]);
});

it('never joins two sentences across the pause between them', function (): void {
    // A full stop is an editorial decision already made by the speaker. Removing
    // the breath after it would run two sentences together.
    $transcript = transcriptOf([
        ['Decidimos.', 0, 600],
        ['Vamos', 1_200, 1_600],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::RoboticPause]))->toBe([]);
});

it('leaves a real pause to silence removal instead', function (): void {
    // Past a second this is not robotic delivery, it is a pause; a different
    // rule owns it and a different target applies to it.
    $transcript = transcriptOf([
        ['vamos', 0, 500],
        ['a', 1_700, 2_100],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::RoboticPause]))->toBe([]);
});

it('does not touch the silence while the AI is generating', function (): void {
    // "enviar el prompt" is followed by real waiting, not by a robotic gap, and
    // closing it would cut into the generation.
    $transcript = transcriptOf([
        ['vamos', 0, 300],
        ['a', 300, 400],
        ['enviar', 400, 800],
        ['el', 800, 900],
        ['prompt', 900, 1_200],
        ['Observemos', 2_000, 2_400],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::RoboticPause]))->toBe([]);
});

it('recognises the same wait when the speaker says "enviemos"', function (): void {
    $transcript = transcriptOf([
        ['enviemos', 0, 400],
        ['el', 400, 500],
        ['prompt.', 500, 900],
        ['Observemos', 1_700, 2_100],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::RoboticPause]))->toBe([]);
});

it('cuts the same gap once the prompt is no longer the last thing said', function (): void {
    // The guard is about what precedes the gap, not about the word "prompt":
    // a gap further into the narration is ordinary robotic spacing again.
    $transcript = transcriptOf([
        ['enviemos', 0, 400],
        ['el', 400, 500],
        ['prompt', 500, 900],
        ['y', 900, 1_000],
        ['esperamos', 1_800, 2_400],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::RoboticPause]))->toHaveCount(1);
});

it('never cuts on air beside a bracketed sound or an unsure word', function (): void {
    $bracketed = transcriptOf([
        ['[laughs]', 0, 500],
        ['hola', 900, 1_300],
    ]);
    $unsure = transcriptOf([
        ['esto', 0, 500, 0.2],
        ['funciona', 900, 1_300, 0.9],
    ]);

    expect(detector()->detect($bracketed, [SpeechCategory::RoboticPause]))->toBe([])
        ->and(detector()->detect($unsure, [SpeechCategory::RoboticPause]))->toBe([]);
});

it('stays off unless the category is asked for', function (): void {
    $transcript = transcriptOf([
        ['vamos', 0, 500],
        ['a', 900, 1_000],
    ]);

    expect(detector()->detect($transcript, [SpeechCategory::Filler]))->toBe([])
        ->and(detector()->detect($transcript, SpeechCategory::all()))->toHaveCount(1);
});

it('persists the robotic gap under a cut reason a cut may be proposed for', function (): void {
    expect(SpeechCategory::RoboticPause->toCutReason())->toBe(CutReason::RoboticPause)
        ->and(SpeechCategory::RoboticPause->value)->toBe('robotic_pause')
        ->and(SpeechCategory::values())->toContain('robotic_pause')
        ->and(CutReason::RoboticPause->value)->toBe('robotic_pause')
        // It is a detector finding, not a model judgement: the AI may never
        // invent one, but a cut can still be proposed with it.
        ->and(CutReason::RoboticPause->isAiProposable())->toBeFalse();
});
