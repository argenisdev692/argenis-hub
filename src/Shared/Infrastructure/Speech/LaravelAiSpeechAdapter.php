<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Speech;

use Illuminate\Contracts\Config\Repository as Config;
use Laravel\Ai\Audio;
use Shared\Domain\Ports\SpeechSynthesizerPort;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Throwable;

/**
 * SDK-native TTS alternate behind {@see SpeechSynthesizerPort}.
 *
 * Same degrade-on-failure contract as the ElevenLabs default: returns null
 * (never throws) so the voiceover stays an optional enhancement. Selected
 * with `services.speech.synthesizer = laravel-ai`; ElevenLabs remains the
 * default so existing behavior is untouched until ops flips the switch —
 * useful when the ElevenLabs quota is exhausted but an `ai.default_for_audio`
 * provider is available.
 */
final readonly class LaravelAiSpeechAdapter implements SpeechSynthesizerPort
{
    public function __construct(
        private CircuitBreakerInterface $breaker,
        private Config $config,
    ) {}

    public function synthesize(string $text): ?array
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            return $this->breaker->call('speech:laravel-ai', fn (): array => $this->generate($text));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{base64: string, mime: string}
     */
    private function generate(string $text): array
    {
        $audio = Audio::of($text)
            ->timeout((int) $this->config->get('services.speech.timeout', 30))
            ->generate((string) $this->config->get('ai.default_for_audio', 'openai'));

        return [
            'base64' => base64_encode($audio->content()),
            'mime' => $audio->mimeType() ?? 'audio/mpeg',
        ];
    }
}
