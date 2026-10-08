<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Services\RestartDetector;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

function restartDetector(): RestartDetector
{
    return new RestartDetector(
        minPhraseWords: 5,
        maxPhraseWords: 8,
        minGapMs: 700,
        maxRepeatDistanceMs: 60_000,
        clusterMs: 2_000,
        maxInterludeWords: 20,
    );
}

/**
 * The shape this application's own transcript actually has: an abandoned clause,
 * then the spoken marker — which Whisper garbled into "vos acá" instead of
 * dropping — then the same clause said again.
 *
 * @param  list<string>  $clause
 * @return list<TranscriptWord>
 */
function abandonedThenRestarted(array $clause, array $interlude, int $failedStartMs = 0): array
{
    $words = [];
    $at = $failedStartMs;

    foreach ($clause as $text) {
        $words[] = new TranscriptWord($text, $at, $at + 350);
        $at += 400;
    }

    $at += 900;

    foreach ($interlude as $text) {
        $words[] = new TranscriptWord($text, $at, $at + 60);
        $at += 400;
    }

    $at += 700;

    foreach ($clause as $text) {
        $words[] = new TranscriptWord($text, $at, $at + 350);
        $at += 400;
    }

    return $words;
}

it('finds a clause said again after a hesitation', function (): void {
    $clause = ['mientras', 'genera', 'fíjate', 'en', 'todo', 'lo', 'que', 'Cloud', 'no', 'sabe'];

    $restarts = restartDetector()->detect(abandonedThenRestarted($clause, ['vos', 'acá']));

    expect($restarts)->toHaveCount(1)
        ->and($restarts[0]->failedStartMs)->toBe(0)
        ->and($restarts[0]->restartStartMs)->toBeGreaterThan(0)
        ->and($restarts[0]->gapMs)->toBeGreaterThanOrEqual(700)
        ->and($restarts[0]->text)->toContain('genera');
});

it('finds it even when the marker was not transcribed at all', function (): void {
    $clause = ['mientras', 'genera', 'fíjate', 'en', 'todo', 'lo', 'que', 'Cloud', 'no', 'sabe'];

    expect(restartDetector()->detect(abandonedThenRestarted($clause, [])))->toHaveCount(1);
});

it('ignores a callback, where a long unrelated passage sits in between', function (): void {
    // The same clause again much later, also preceded by a long gap. The silence
    // alone cannot tell this from a restart — what separates them is that twenty
    // words were said in between rather than two.
    $phrase = ['esto', 'es', 'importante', 'porque', 'cambia'];

    $words = abandonedThenRestarted(
        [...$phrase, 'el', 'flujo', 'de', 'trabajo'],
        ['Una', 'idea', 'completamente', 'distinta', 'que', 'no', 'tiene', 'nada', 'que', 'ver', 'con', 'lo', 'anterior', 'y', 'tampoco', 'con', 'lo', 'que', 'viene', 'después', 'de', 'esto', 'que', 'digo', 'ahora'],
    );

    expect(restartDetector()->detect($words))->toBe([]);
});

it('ignores a repeat that follows immediately', function (): void {
    $words = [];
    $at = 0;

    foreach (array_merge(
        ['Este', 'es', 'el', 'paso', 'que', 'casi', 'nadie', 'da'],
        ['Este', 'es', 'el', 'paso', 'que', 'casi', 'nadie', 'da'],
    ) as $text) {
        $words[] = new TranscriptWord($text, $at, $at + 350);
        $at += 400;
    }

    expect(restartDetector()->detect($words))->toBe([]);
});

it('collapses one restart into a single proposal however many phrase lengths fit', function (): void {
    // Every window of 4..8 words inside the same restart matches, at several
    // offsets. Without clustering the owner would approve the same cut five times.
    $restarts = restartDetector()->detect(abandonedThenRestarted(
        ['empezar', 'de', 'nuevo', 'explicándolo', 'todo', 'otra', 'vez'],
        ['otra', 'vez'],
    ));

    expect($restarts)->toHaveCount(1);
});

it('treats a phrase said three times as a refrain and leaves it alone', function (): void {
    $refrain = ['esto', 'es', 'lo', 'que', 'matters'];

    $words = abandonedThenRestarted($refrain, []);
    $at = 40_000;

    foreach ($refrain as $text) {
        $words[] = new TranscriptWord($text, $at, $at + 350);
        $at += 400;
    }

    expect(restartDetector()->detect($words))->toBe([]);
});

it('will not pair two copies too far apart to be one attempt', function (): void {
    $clause = ['mismo', 'problema', 'de', 'siempre', 'con', 'estos', 'datos'];

    $words = [];
    $at = 0;

    foreach ($clause as $text) {
        $words[] = new TranscriptWord($text, $at, $at + 350);
        $at += 400;
    }

    $at = 120_000;

    foreach ($clause as $text) {
        $words[] = new TranscriptWord($text, $at, $at + 350);
        $at += 400;
    }

    expect(restartDetector()->detect($words))->toBe([]);
});

it('will not match a run made only of function words', function (): void {
    $grammar = ['de', 'la', 'que', 'con', 'el', 'por', 'para'];

    expect(restartDetector()->detect(abandonedThenRestarted($grammar, [])))->toBe([]);
});

it('reports nothing for an empty transcript or an impossible configuration', function (): void {
    $disabled = new RestartDetector(9, 8, 700, 60_000, 2_000, 20);

    expect(restartDetector()->detect([]))->toBe([])
        ->and($disabled->detect(abandonedThenRestarted(['uno', 'dos', 'tres', 'cuatro'], [])))->toBe([]);
});
