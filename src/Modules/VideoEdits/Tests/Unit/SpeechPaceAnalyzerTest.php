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

/**
 * A recording that is deliberately uneven: a calm baseline and one stretch where
 * the speaker walks through the steps of an example without stopping.
 *
 * @param  int  $rushedFrom  first word index of the fast stretch
 * @param  int  $rushedTo  last word index of the fast stretch
 */
function unevenTranscript(int $totalWords, int $rushedFrom, int $rushedTo, int $slowWordMs = 500, int $rushedWordMs = 180): Transcript
{
    $words = [];
    $cursor = 0;

    for ($index = 0; $index < $totalWords; $index++) {
        $duration = $index >= $rushedFrom && $index <= $rushedTo ? $rushedWordMs : $slowWordMs;
        $words[] = new TranscriptWord('palabra', $cursor, $cursor + $duration);
        $cursor += $duration;
    }

    return new Transcript($words, language: 'es');
}

it('finds a stretch walked through far faster than the rest of the recording', function (): void {
    // 120 words at 500 ms ≈ 120 words/min baseline; words 40–79 at 180 ms
    // ≈ 333 words/min, which clears both the 1.35× ratio and the 190 floor.
    $stretches = paceAnalyzer()->rushedStretches(unevenTranscript(120, 40, 79));

    expect($stretches)->toBe([[20_000, 27_200]]);
});

it('bounds a rushed stretch to the words that actually sped up', function (): void {
    // No window bleed: the run starts on the first fast word and ends on the
    // last, never on the slow word that closed it — advice with the wrong
    // timecode is worse than no advice at all.
    expect(paceAnalyzer()->rushedStretches(unevenTranscript(120, 40, 79))[0])
        ->toBe([40 * 500, 40 * 500 + 40 * 180]);
});

it('reports a rushed stretch with a timecode the speaker can re-record against', function (): void {
    $recommendation = paceAnalyzer()->rushRecommendation(unevenTranscript(120, 40, 79));

    expect($recommendation?->kind)->toBe(AiRecommendationKind::RushedStep)
        ->and($recommendation->title)->toContain('1 tramo atropellado')
        ->and($recommendation->detail)->toContain('0:20–0:27')
        ->and($recommendation->detail)->toContain('se pierde')
        ->and($recommendation->startMs)->toBe(20_000)
        ->and($recommendation->endMs)->toBe(27_200);
});

it('says nothing when the delivery is even throughout', function (): void {
    // The observation is a recording that sprints through the part that matters,
    // not one that is simply quick. A uniformly fast recording is not a defect.
    expect(paceAnalyzer()->rushedStretches(pacedTranscript(200)))->toBe([])
        ->and(paceAnalyzer()->rushRecommendation(pacedTranscript(200)))->toBeNull();
});

it('does not flag a slow speaker for speaking at their own normal speed', function (): void {
    // 200 words at 700 ms ≈ 86 words/min throughout: slow, but internally even.
    $words = array_map(
        static fn (int $index): TranscriptWord => new TranscriptWord('palabra', $index * 700, ($index * 700) + 700),
        range(0, 199),
    );

    expect(paceAnalyzer()->rushedStretches(new Transcript($words, language: 'es')))->toBe([]);
});

it('needs the fast stretch to last long enough to lose a viewer', function (): void {
    // Only 8 words run fast: enough to clear the word count, but 1.4 s of
    // urgency is one hurried sentence, not a streak a viewer gets lost in.
    expect(paceAnalyzer()->rushedStretches(unevenTranscript(120, 40, 47)))->toBe([]);
});

it('ignores bracketed sounds, which are not words to keep up with', function (): void {
    $words = pacedTranscript(120)->words;
    $words[10] = new TranscriptWord('[laughs]', $words[10]->startMs, $words[10]->endMs);

    expect(paceAnalyzer()->rushedStretches(new Transcript($words, language: 'es')))->toBe([]);
});

it('writes the rushed finding in English for an English recording', function (): void {
    $recommendation = paceAnalyzer()->rushRecommendation(
        new Transcript(unevenTranscript(120, 40, 79)->words, language: 'en'),
    );

    expect($recommendation?->title)->toStartWith('1 rushed stretch')
        ->and($recommendation->detail)->toContain('gets lost');
});
