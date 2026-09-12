<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Modules\VideoEdits\Domain\Exceptions\TranscriptionFailedException;
use Modules\VideoEdits\Domain\Ports\TranscriptionPort;
use Modules\VideoEdits\Domain\ValueObjects\Transcript;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptSegment;
use Modules\VideoEdits\Domain\ValueObjects\TranscriptWord;

beforeEach(function (): void {
    config()->set('video-edit.speech.openai.api_key', 'sk-test');
    config()->set('video-edit.speech.openai.model', 'whisper-1');

    $this->audioPath = tempnam(sys_get_temp_dir(), 'vet').'.mp3';
    file_put_contents($this->audioPath, 'fake audio bytes');
});

afterEach(function (): void {
    @unlink($this->audioPath);
});

/**
 * @param  array<string, mixed>  $payload
 */
function fakeWhisper(array $payload, int $status = 200): void
{
    Http::fake(['api.openai.com/*' => Http::response($payload, $status)]);
}

it('asks Whisper for word-level timestamps', function (): void {
    fakeWhisper([
        'language' => 'spanish',
        'words' => [['word' => 'Hola', 'start' => 0.0, 'end' => 0.4]],
        'segments' => [['text' => 'Hola', 'start' => 0.0, 'end' => 0.4]],
    ]);

    app(TranscriptionPort::class)->transcribe($this->audioPath, 'es');

    Http::assertSent(function ($request): bool {
        $body = collect($request->data())->pluck('contents', 'name');
        $granularities = collect($request->data())
            ->where('name', 'timestamp_granularities[]')
            ->pluck('contents')
            ->all();

        // Word granularity is the whole reason this adapter bypasses the SDK.
        return in_array('word', $granularities, true)
            && $body['response_format'] === 'verbose_json'
            && $body['model'] === 'whisper-1'
            && $body['language'] === 'es';
    });
});

it('converts Whisper seconds into the domain\'s milliseconds', function (): void {
    fakeWhisper([
        'language' => 'spanish',
        'words' => [
            ['word' => 'Hola', 'start' => 0.0, 'end' => 0.42],
            ['word' => 'eh', 'start' => 1.255, 'end' => 1.5],
        ],
        'segments' => [['text' => 'Hola eh', 'start' => 0.0, 'end' => 1.5]],
    ]);

    $transcript = app(TranscriptionPort::class)->transcribe($this->audioPath);

    expect($transcript->wordCount())->toBe(2)
        ->and($transcript->words[0]->endMs)->toBe(420)
        ->and($transcript->words[1]->startMs)->toBe(1_255)
        ->and($transcript->language)->toBe('spanish')
        ->and($transcript->segments[0]->endMs)->toBe(1_500);
});

it('fails loudly when the provider returns no word timings', function (): void {
    // Silently returning zero detections would hand the user an "edited" video
    // that was never edited.
    fakeWhisper(['language' => 'spanish', 'text' => 'Hola', 'segments' => []]);

    expect(fn () => app(TranscriptionPort::class)->transcribe($this->audioPath))
        ->toThrow(TranscriptionFailedException::class);
});

it('reports a provider rejection without leaking its body', function (): void {
    fakeWhisper(['error' => ['message' => 'internal path /srv/secret']], 429);

    expect(fn () => app(TranscriptionPort::class)->transcribe($this->audioPath))
        ->toThrow(TranscriptionFailedException::class, 'HTTP 429');
});

it('refuses to call the provider without an API key', function (): void {
    config()->set('video-edit.speech.openai.api_key', '');
    Http::fake();

    expect(fn () => app(TranscriptionPort::class)->transcribe($this->audioPath))
        ->toThrow(TranscriptionFailedException::class);

    Http::assertNothingSent();
});

it('round-trips a transcript through storage without losing word timings', function (): void {
    $original = new Transcript(
        words: [new TranscriptWord('eh', 1_255, 1_500, 0.8)],
        segments: [new TranscriptSegment('eh', 1_255, 1_500)],
        language: 'es',
    );

    $restored = Transcript::fromArray($original->toArray());

    expect($restored->words[0]->startMs)->toBe(1_255)
        ->and($restored->words[0]->endMs)->toBe(1_500)
        ->and($restored->words[0]->confidence)->toBe(0.8)
        ->and($restored->language)->toBe('es');
});
