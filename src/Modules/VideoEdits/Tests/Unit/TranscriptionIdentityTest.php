<?php

declare(strict_types=1);

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Modules\VideoEdits\Application\Transcription\TranscriptionIdentity;

function identityFor(array $config): TranscriptionIdentity
{
    return new TranscriptionIdentity(new class($config) implements ConfigRepository
    {
        /** @param array<string, mixed> $values */
        public function __construct(private array $values) {}

        public function has($key): bool
        {
            return array_key_exists($key, $this->values);
        }

        public function get($key, $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function all(): array
        {
            return $this->values;
        }

        public function set($key, $value = null): void
        {
            $this->values[$key] = $value;
        }

        public function prepend($key, $value): void {}

        public function push($key, $value): void {}
    });
}

it('reads the provider and the model that belongs to it', function (): void {
    $identity = identityFor([
        'video-edit.provider' => 'gemini',
        'video-edit.speech.gemini.model' => 'gemini-3.5-transcribe',
        'video-edit.speech.openai.model' => 'whisper-1',
    ]);

    expect($identity->provider())->toBe('gemini')
        ->and($identity->model())->toBe('gemini-3.5-transcribe');
});

/**
 * The failure this exists to prevent: the model is read from the wrong
 * provider's key, which is what left every stored transcript labelled
 * `openai`/`whisper-1` no matter which transcriber produced it.
 */
it('never pairs one provider with another provider model', function (): void {
    $identity = identityFor([
        'video-edit.provider' => 'openai',
        'video-edit.speech.openai.model' => 'whisper-1',
        'video-edit.speech.gemini.model' => 'gemini-3.5-transcribe',
    ]);

    expect($identity->model())->toBe('whisper-1')
        ->and($identity->model())->not->toBe('gemini-3.5-transcribe');
});

it('defaults to the openai provider the module ships on', function (): void {
    $identity = identityFor(['video-edit.speech.openai.model' => 'whisper-1']);

    expect($identity->provider())->toBe('openai');
});

it('rejects an unknown provider instead of storing a transcript nobody can find', function (): void {
    identityFor(['video-edit.provider' => 'deepgram'])->provider();
})->throws(InvalidArgumentException::class);

it('refuses to guess when the configured model key is missing', function (): void {
    // An empty model would be written to the row and then never match on reuse,
    // so every run of that edit would re-transcribe and pay for it again.
    expect(identityFor(['video-edit.provider' => 'gemini'])->model())->toBe('');
});

/*
|----------------------------------------------------------------------
| The path every provider block has to live at
|----------------------------------------------------------------------
|
| `GeminiTranscriber` and `TranscriptionIdentity` both read
| `video-edit.speech.<provider>.*`. The gemini block shipped one level up at
| `video-edit.gemini.*`, so the key came back null and the adapter threw "no
| Gemini API key is configured" on every call — while its own test set the same
| wrong key and passed. A test that mirrors the reader cannot catch a reader
| that is wrong; this one reads the shipped config instead.
*/
it('resolves both provider blocks through the path the adapters read', function (): void {
    foreach (['openai', 'gemini'] as $provider) {
        config()->set('video-edit.provider', $provider);

        expect(config('video-edit.speech.'.$provider.'.model'))
            ->toBeString()
            ->not->toBeEmpty()
            ->and(config('video-edit.speech.'.$provider.'.api_key'))
            ->toBeString();
    }
});
