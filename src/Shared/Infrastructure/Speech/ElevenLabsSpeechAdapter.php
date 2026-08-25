<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Speech;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Shared\Domain\Ports\SpeechSynthesizerPort;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Throwable;

/**
 * @see SpeechSynthesizerPort
 */
final readonly class ElevenLabsSpeechAdapter implements SpeechSynthesizerPort
{
    private const int TIMEOUT_SECONDS = 30;

    public function __construct(private CircuitBreakerInterface $breaker) {}

    public function synthesize(string $text): ?array
    {
        $apiKey = (string) config('services.elevenlabs.api_key');
        $voiceId = (string) config('services.elevenlabs.voice_id');

        if ($apiKey === '' || $voiceId === '') {
            return null;
        }

        return $this->breaker->call(
            'elevenlabs',
            function () use ($apiKey, $voiceId, $text): array {
                $response = Http::withHeaders([
                    'xi-api-key' => $apiKey,
                    'Accept' => 'audio/mpeg',
                ])
                    ->timeout(self::TIMEOUT_SECONDS)
                    ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                        'text' => $text,
                        'model_id' => (string) config('services.elevenlabs.model_id', 'eleven_multilingual_v2'),
                    ]);

                if ($response->failed()) {
                    throw new RuntimeException("ElevenLabs synthesis failed with status {$response->status()}.");
                }

                return [
                    'base64' => base64_encode($response->body()),
                    'mime' => 'audio/mpeg',
                ];
            },
            function (Throwable $e): ?array {
                Log::warning('elevenlabs.synthesis_failed', ['error' => $e->getMessage()]);

                return null;
            },
        );
    }
}
