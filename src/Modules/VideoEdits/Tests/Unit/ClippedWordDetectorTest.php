<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Services\ClippedWordDetector;
use Modules\VideoEdits\Domain\ValueObjects\AiCutProposal;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

/**
 * A transcript of whole words with the given fragments marked, one every 500 ms,
 * so the clipped ones can be placed by index.
 *
 * @param  list<int>  $clippedIndexes
 */
function clippedTranscript(array $clippedIndexes, ?string $language = 'es'): Transcript
{
    $words = [];

    for ($index = 0; $index < 12; $index++) {
        $words[] = new TranscriptWord(
            in_array($index, $clippedIndexes, true) ? 'co-' : 'palabra',
            $index * 500,
            ($index * 500) + 300,
        );
    }

    return new Transcript($words, language: $language);
}

function cutProposal(int $startWordIndex, int $endWordIndex): AiCutProposal
{
    return new AiCutProposal(
        reason: CutReason::Retake,
        startWordIndex: $startWordIndex,
        endWordIndex: $endWordIndex,
        confidence: 0.9,
        evidence: 'test',
    );
}

it('finds the words the microphone never finished', function (): void {
    $clipped = (new ClippedWordDetector(minClippedWords: 1))->clippedWords(clippedTranscript([2, 5, 9]));

    expect($clipped)->toHaveCount(3)
        ->and(array_map(static fn (TranscriptWord $word): string => $word->text, $clipped))
        ->toBe(['co-', 'co-', 'co-']);
});

it('reports the count and the moment, so the speaker can aim the re-record', function (): void {
    $recommendation = (new ClippedWordDetector(minClippedWords: 3))->recommendation(clippedTranscript([2, 5, 9]));

    expect($recommendation?->kind)->toBe(AiRecommendationKind::UnclearWord)
        ->and($recommendation->title)->toBe('3 palabras cortadas por el micrófono')
        ->and($recommendation->detail)->toContain('"co-" (0:01)')
        ->and($recommendation->detail)->toContain('0:04')
        // The remedy is a desk change, and naming where is the whole value of
        // the row: this setup's controls are the Fifine gain knob and the OBS
        // compressor, not a preamp that does not exist on this chain.
        ->and($recommendation->detail)->toContain('perilla del Fifine')
        ->and($recommendation->detail)->toContain('Compressor de OBS')
        ->and($recommendation->startMs)->toBe(1_000);
});

it('does not count a fragment the pipeline is about to cut anyway', function (): void {
    // Words 2–5 fall inside a proposed cut, which CutEdgeTrimmer already
    // absorbs — reporting them would name a fault that is already fixed.
    $clipped = (new ClippedWordDetector(minClippedWords: 1))
        ->clippedWords(clippedTranscript([2, 5, 9]), [cutProposal(2, 5)]);

    expect(array_map(static fn (TranscriptWord $word): int => $word->startMs, $clipped))->toBe([4_500]);
});

it('keeps the floor on a single clipped fragment', function (): void {
    // One truncated fragment is what any normal false start produces; a handful
    // is a pattern, and a pattern is what changes someone's microphone setup.
    expect((new ClippedWordDetector(minClippedWords: 3))->recommendation(clippedTranscript([4])))->toBeNull()
        ->and((new ClippedWordDetector(minClippedWords: 3))->recommendation(clippedTranscript([2, 5, 9])))->not->toBeNull();
});

it('ignores a bracketed sound, however it is punctuated', function (): void {
    $words = clippedTranscript([2, 5, 9])->words;
    $words[7] = new TranscriptWord('[laughs -]', 3_500, 3_800);

    expect((new ClippedWordDetector(minClippedWords: 1))->clippedWords(new Transcript($words, language: 'es')))
        ->toHaveCount(3);
});

it('ignores an out-of-bounds proposal rather than indexing past the transcript', function (): void {
    $clipped = (new ClippedWordDetector(minClippedWords: 1))
        ->clippedWords(clippedTranscript([2, 5, 9]), [cutProposal(400, 900)]);

    expect($clipped)->toHaveCount(3);
});

it('writes the clipped finding in English for an English recording', function (): void {
    $recommendation = (new ClippedWordDetector(minClippedWords: 3))
        ->recommendation(clippedTranscript([2, 5, 9], language: 'en'));

    expect($recommendation?->title)->toBe('3 words clipped by the microphone')
        ->and($recommendation->detail)->toContain('mic gain knob')
        ->and($recommendation->detail)->toContain('OBS Compressor');
});

it('caps the listed fragments so the report stays a checklist', function (): void {
    $recommendation = (new ClippedWordDetector(minClippedWords: 3, maxReportedWords: 2))
        ->recommendation(clippedTranscript([1, 2, 3, 4, 5]));

    expect($recommendation?->detail)->toContain('y 3 más');
});
