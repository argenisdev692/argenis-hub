<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI;

use Laravel\Ai\Responses\StructuredAgentResponse;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreaker;

/**
 * Sole LLM bridge for the application (ARCHITECTURE-PHP §"Infrastructure/AI").
 * Wraps the official `laravel/ai` SDK so the provider switch (OpenAI / Anthropic
 * / Gemini) happens entirely via `config('ai.php')` + a runtime override string —
 * callers never touch a provider SDK directly.
 *
 * Resilience: every call is already wrapped in {@see CircuitBreaker}
 * inside the bound implementation ({@see LaravelAIAdapter}) — callers never
 * wrap this interface with their own breaker. Per-route `throttle:` guards
 * cover LLM10 Unbounded Consumption on top of that (see Post/SocialMedia/
 * Campaigns AI-assist routes). Result caching is a separate, deliberately
 * NOT-bundled concern: it lives in each module's own `LaravelAi*Adapter`
 * (business-specific keys/TTL/invalidation), never here — this interface
 * stays a pure, uncached transport.
 */
interface AIClientInterface
{
    /**
     * Invoke a structured-output Agent (implements `Agent` + `HasStructuredOutput`)
     * and return its schema-validated response. Access fields via
     * `$response['field']` (StructuredAgentResponse is array-accessible).
     *
     * @param  class-string  $agentClass
     */
    public function generateStructured(string $agentClass, string $prompt, ?string $provider = null): StructuredAgentResponse;

    /**
     * Generate a single image and return its raw bytes (never persisted here —
     * the caller decides the StoragePort destination and visibility). The
     * installed `laravel/ai` 0.8.x `GeneratedImage` DTO exposes only base64
     * content + MIME — no revised-prompt/URL fields — so neither is returned.
     *
     * @return array{base64: string, mime: string}
     */
    public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array;
}
