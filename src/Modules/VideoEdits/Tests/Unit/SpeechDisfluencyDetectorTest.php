<?php

declare(strict_types=1);

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
