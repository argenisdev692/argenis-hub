<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\VideoEdits\Domain\Exceptions\TranscriptionFailedException;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\Ports\VideoEditorPort;
use Modules\VideoEdits\Domain\ValueObjects\MediaProbe;
use Modules\VideoEdits\Infrastructure\Transcription\GeminiTranscriber;
use Modules\VideoEdits\Infrastructure\Transcription\OpenAiWhisperTranscriber;
use Modules\VideoEdits\Tests\Support\FakeVideoEditor;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('video-edit.speech.gemini.api_key', 'test-key');

    // The adapter uploads the audio it is handed, so the path has to exist. The
    // basename is what the fake editor keys its probes by.
    $this->audioDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'video-edit-gemini-'.bin2hex(random_bytes(4));
    mkdir($this->audioDirectory);
    $this->audioPath = $this->audioDirectory.DIRECTORY_SEPARATOR.'speech-audio.mp3';
    file_put_contents($this->audioPath, 'audio-bytes');

    $this->editor = new FakeVideoEditor;
    $this->editor->probes = [
        'speech-audio.mp3' => new MediaProbe(600_000, 'mp3', hasVideo: false, hasAudio: true),
    ];
    app()->instance(VideoEditorPort::class, $this->editor);
});

afterEach(function (): void {
    File::deleteDirectory($this->audioDirectory);
});

/**
 * The response shape the Interactions API documents: `word_info` annotations whose
 * offsets are STRINGS. That detail is the whole reason the parser exists.
 *
 * @param  list<array{text: string, start: string, end: string}>  $words
 * @return array<string, mixed>
 */
function geminiInteractionResponse(array $words): array
{
    return [
        'id' => 'interactions/abc',
        'status' => 'completed',
        'steps' => [[
            'id' => 'step_001',
            'type' => 'model_output',
            'content' => [[
                'type' => 'text',
                'text' => implode(' ', array_column($words, 'text')),
                'annotations' => array_map(
                    static fn (array $word): array => [
                        'type' => 'word_info',
                        'text' => $word['text'],
                        'start_offset' => $word['start'],
                        'end_offset' => $word['end'],
                    ],
                    $words,
                ),
            ]],
        ]],
    ];
}

/**
 * Fakes the two Files API calls and an ordered run of interaction responses.
 *
 * @param  list<array<string, mixed>>  $interactionBodies
 */
function fakeGeminiApi(array $interactionBodies): void
{
    $interactions = Http::sequence();

    foreach ($interactionBodies as $body) {
        $interactions = $interactions->push($body);
    }

    Http::fake([
        'generativelanguage.googleapis.com/upload/v1beta/files' => Http::response(
            ['name' => 'files/abc'],
            200,
            ['Location' => 'https://generativelanguage.googleapis.com/upload/v1beta/files/abc?upload_id=1'],
        ),
        'generativelanguage.googleapis.com/upload/v1beta/files/abc?upload_id=1' => Http::response(
            ['file' => ['uri' => 'https://files/abc']],
            200,
        ),
        'generativelanguage.googleapis.com/v1beta/interactions*' => $interactions,
    ]);
}

it('resolves to Whisper by default and to Gemini when configured', function (): void {
    expect(app(TranscriptionPort::class))->toBeInstanceOf(OpenAiWhisperTranscriber::class);

    config()->set('video-edit.provider', 'gemini');

    expect(app(TranscriptionPort::class))->toBeInstanceOf(GeminiTranscriber::class);
});

it('refuses an unknown provider instead of silently falling back', function (): void {
    // A typo that fell back to Whisper would stay invisible until the date Whisper
    // is switched off, which is the worst possible moment to discover it.
    config()->set('video-edit.provider', 'whispr');

    expect(fn () => app(TranscriptionPort::class))->toThrow(InvalidArgumentException::class);
});

it('asks for verbatim word timestamps and returns them as timings', function (): void {
    fakeGeminiApi([geminiInteractionResponse([
        ['text' => 'Pausa', 'start' => '0.500s', 'end' => '0.980s'],
        ['text' => 'acá', 'start' => '0.980s', 'end' => '1.240s'],
    ])]);

    $transcript = app(GeminiTranscriber::class)->transcribe($this->audioPath, 'es');

    expect($transcript->wordCount())->toBe(2)
        ->and($transcript->words[0]->text)->toBe('Pausa')
        // "0.500s" cast to an int is 0, which would collapse the transcript onto
        // its first instant and silently disable every cut in the module.
        ->and($transcript->words[0]->startMs)->toBe(500)
        ->and($transcript->words[0]->endMs)->toBe(980)
        ->and($transcript->words[1]->endMs)->toBe(1_240);

    Http::assertSent(function (Request $request): bool {
        if (! str_contains($request->url(), '/v1beta/interactions')) {
            return false;
        }

        $body = json_decode($request->body(), true);
        $config = $body['generation_config']['transcription_config'] ?? [];

        return $body['model'] === 'gemini-3.5-transcribe'
            // verbatim, never smart: smart strips the fillers this module exists to
            // cut, and cannot be combined with timestamps anyway.
            && $config['mode']['type'] === 'verbatim'
            && $config['mode']['timestamp_granularities'] === ['word']
            && $config['language_codes'] === ['es-ES']
            && $body['input'][0]['mime_type'] === 'audio/mp3';
    });
});

it('slices a long recording and rebases every chunk onto the full timeline', function (): void {
    // Thirty minutes is Gemini's cap once word timestamps are on; this module
    // allows ninety, so the chunking is not optional and the rebasing is its point.
    $this->editor->probes = [
        'speech-audio.mp3' => new MediaProbe(3_600_000, 'mp3', hasVideo: false, hasAudio: true),
    ];
    config()->set('video-edit.speech.gemini.chunk_seconds', 1_500);

    fakeGeminiApi([
        geminiInteractionResponse([['text' => 'primero', 'start' => '0.000s', 'end' => '0.500s']]),
        geminiInteractionResponse([['text' => 'segundo', 'start' => '0.000s', 'end' => '0.500s']]),
        geminiInteractionResponse([['text' => 'tercero', 'start' => '0.000s', 'end' => '0.500s']]),
    ]);

    $transcript = app(GeminiTranscriber::class)->transcribe($this->audioPath, 'es');

    expect($this->editor->callsTo('sliceAudio'))->toHaveCount(3)
        ->and($transcript->words[0]->startMs)->toBe(0)
        // Without the rebasing every word after the first chunk would sit at its
        // position inside its own chunk rather than inside the recording.
        ->and($transcript->words[1]->startMs)->toBe(1_500_000)
        ->and($transcript->words[2]->startMs)->toBe(3_000_000)
        ->and($transcript->words[2]->text)->toBe('tercero');
});

it('sends a short recording in one request without slicing it', function (): void {
    fakeGeminiApi([geminiInteractionResponse([['text' => 'hola', 'start' => '0.000s', 'end' => '0.400s']])]);

    app(GeminiTranscriber::class)->transcribe($this->audioPath, 'es');

    expect($this->editor->called('sliceAudio'))->toBeFalse();
});

it('fails loudly rather than returning a transcript with no timings', function (): void {
    fakeGeminiApi([['id' => 'interactions/abc', 'status' => 'completed', 'steps' => []]]);

    expect(fn () => app(GeminiTranscriber::class)->transcribe($this->audioPath, 'es'))
        ->toThrow(TranscriptionFailedException::class);
});

it('drops an annotation whose timing cannot be parsed instead of guessing it', function (): void {
    $parsed = GeminiTranscriber::toTranscript(geminiInteractionResponse([
        ['text' => 'bien', 'start' => '1.000s', 'end' => '1.400s'],
        ['text' => 'raro', 'start' => 'no-es-un-offset', 'end' => '2.000s'],
        ['text' => '', 'start' => '2.000s', 'end' => '2.400s'],
    ]));

    expect($parsed['words'])->toHaveCount(1)
        ->and($parsed['words'][0]['text'])->toBe('bien');
});

it('parses every offset unit Gemini documents', function (): void {
    $payload = [
        'steps' => [[
            'content' => [[
                'annotations' => [
                    ['type' => 'word_info', 'text' => 'a', 'start_offset' => '0.100s', 'end_offset' => '0.150s'],
                    ['type' => 'word_info', 'text' => 'b', 'start_offset' => '1500ms', 'end_offset' => '1600ms'],
                    ['type' => 'word_info', 'text' => 'c', 'start_offset' => '1m30s', 'end_offset' => '1m31s'],
                    ['type' => 'word_info', 'text' => 'd', 'start_offset' => '1h2m3s', 'end_offset' => '1h2m4s'],
                ],
            ]],
        ]],
    ];

    $starts = array_column(GeminiTranscriber::toTranscript($payload)['words'], 'start_ms', 'text');

    // "1m30s" is the case a suffix-stripping parser silently drops, because the
    // value carries two fields and ends in neither unit.
    expect($starts)->toBe(['a' => 100, 'b' => 1_500, 'c' => 90_000, 'd' => 3_723_000]);
});

it('requires an API key before touching the network', function (): void {
    config()->set('video-edit.speech.gemini.api_key', '');

    expect(fn () => app(GeminiTranscriber::class)->transcribe($this->audioPath, 'es'))
        ->toThrow(TranscriptionFailedException::class);

    Http::assertNothingSent();
});

it('surfaces a provider rejection rather than returning an empty transcript', function (): void {
    Http::fake([
        'generativelanguage.googleapis.com/upload/*' => Http::response(['error' => 'nope'], 403),
        '*' => Http::response([]),
    ]);

    expect(fn () => app(GeminiTranscriber::class)->transcribe($this->audioPath, 'es'))
        ->toThrow(TranscriptionFailedException::class);
});
