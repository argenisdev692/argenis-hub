<?php

declare(strict_types=1);

use Modules\VideoEdits\Domain\Enums\AiRecommendationKind;
use Modules\VideoEdits\Domain\Services\SpeechPaceAnalyzer;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

function paceAnalyzer(): SpeechPaceAnalyzer
{
    return new SpeechPaceAnalyzer(
        miniPauseMs: 400,
        minMiniPauses: 3,
        slowWordsPerMinute: 130,
        minSpeakingMs: 10_000,
        defaultLanguage: 'es',
    );
}

/**
 * Evenly spoken words of 300 ms, with the given gap before each word index.
 *
 * @param  array<int, int>  $gapsBeforeWord  word index => gap in ms (default 50 ms)
 */
function pacedTranscript(int $wordCount, array $gapsBeforeWord = [], ?string $language = 'es'): Transcript
{
    $words = [];
    $cursor = 0;

    for ($index = 0; $index < $wordCount; $index++) {
        $cursor += $index === 0 ? 0 : ($gapsBeforeWord[$index] ?? 50);
        $words[] = new TranscriptWord('palabra', $cursor, $cursor + 300);
        $cursor += 300;
    }

    return new Transcript($words, language: $language);
}

it('says nothing about a fluid delivery', function (): void {
    // 60 words of 350 ms each ≈ 171 words/min, no pauses.
    expect(paceAnalyzer()->recommendation(pacedTranscript(60), 1_000))->toBeNull();
});

it('counts the mini-pauses the silence threshold leaves in', function (): void {
    // Four 600 ms pauses survive a 1 s threshold; the 1.5 s one is cut by silence removal.
    $transcript = pacedTranscript(60, [10 => 600, 20 => 600, 30 => 600, 40 => 600, 50 => 1_500]);

    $recommendation = paceAnalyzer()->recommendation($transcript, 1_000);

    expect($recommendation?->kind)->toBe(AiRecommendationKind::Pacing)
        ->and($recommendation->title)->toContain('4 minipausas')
        ->and($recommendation->detail)->toContain('suman 2.4 s')
        ->and($recommendation->detail)->toContain('Baja el umbral de silencio a 0.4 s');
});

it('counts every pause when silence removal is off', function (): void {
    $transcript = pacedTranscript(60, [10 => 600, 20 => 1_500, 30 => 2_000]);

    expect(paceAnalyzer()->recommendation($transcript, null)?->title)->toContain('3 minipausas');
});

it('flags a slow delivery even without mini-pauses', function (): void {
    // 30 words spread over ~20 s ≈ 90 words/min; the 350 ms gaps are below the mini-pause length.
    $gaps = array_fill(1, 29, 350);

    $recommendation = paceAnalyzer()->recommendation(pacedTranscript(30, $gaps), 1_000);

    expect($recommendation?->detail)->toContain('por debajo de 130 palabras/min');
});

it('writes the recommendation in English for an English recording', function (): void {
    $transcript = pacedTranscript(60, [10 => 600, 20 => 600, 30 => 600], language: 'en');

    expect(paceAnalyzer()->recommendation($transcript, 1_000)?->title)->toStartWith('Pace: ')->toEndWith('words/min, 3 mini-pauses');
});

it('does not measure a recording too short for a meaningful pace', function (): void {
    expect(paceAnalyzer()->recommendation(pacedTranscript(5, [1 => 900, 2 => 900, 3 => 900]), null))->toBeNull();
});
