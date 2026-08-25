<?php

declare(strict_types=1);

namespace Shared\Domain\Ports;

use Shared\Infrastructure\Research\TavilyClientInterface;

/**
 * Text-to-speech contract (ElevenLabs by default). Domain-pure: only native
 * types. Mirrors {@see TavilyClientInterface}'s
 * degrade-on-failure contract — callers never need a try/catch of their own.
 */
interface SpeechSynthesizerPort
{
    /**
     * Synthesize speech for the given text. Returns null (never throws) when
     * the provider is unreachable, misconfigured, or errors — callers must
     * treat the voiceover as an optional enhancement, not a hard dependency.
     *
     * @return array{base64: string, mime: string}|null
     */
    public function synthesize(string $text): ?array;
}
