<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Tests\Support;

use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Shared\Infrastructure\AI\AIClientInterface;
use Shared\Infrastructure\AI\PromptCache\CacheablePrompt;
use Shared\Infrastructure\AI\PromptCache\PromptCacheScope;

/**
 * Stands in for the shared AI bridge: canned payloads per agent, plus the
 * prompt it was handed and the prompt scope as it stood during the call — the
 * only place an agent's provider cache fields come from.
 */
final class RecordingAiClient implements AIClientInterface
{
    /** @var list<array{agent: string, prompt: string, provider: ?string, model: ?string, timeout: ?int}> */
    public array $calls = [];

    public ?string $lastPrompt = null;

    public ?CacheablePrompt $lastScope = null;

    /**
     * @param  array<class-string, array<string, mixed>>  $payloads
     */
    public function __construct(private array $payloads = []) {}

    /**
     * @param  array<class-string, array<string, mixed>>  $payloads
     */
    public static function install(array $payloads = []): self
    {
        $client = new self($payloads);
        app()->instance(AIClientInterface::class, $client);

        return $client;
    }

    public function generateStructured(string $agentClass, string $prompt, ?string $provider = null, ?string $model = null, ?int $timeoutSeconds = null): StructuredAgentResponse
    {
        $this->calls[] = [
            'agent' => $agentClass,
            'prompt' => $prompt,
            'provider' => $provider,
            'model' => $model,
            'timeout' => $timeoutSeconds,
        ];

        $this->lastPrompt = $prompt;
        $this->lastScope = app(PromptCacheScope::class)->current();

        return new StructuredAgentResponse(
            invocationId: 'test',
            structured: $this->payloads[$agentClass] ?? [],
            text: '',
            usage: new Usage(promptTokens: 8000, completionTokens: 500),
            meta: new Meta(provider: (string) $provider, model: (string) $model),
        );
    }

    public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
    {
        return ['base64' => '', 'mime' => 'image/png'];
    }

    public function requireCall(): void
    {
        if ($this->calls === []) {
            throw new RuntimeException('Expected at least one provider call.');
        }
    }
}
