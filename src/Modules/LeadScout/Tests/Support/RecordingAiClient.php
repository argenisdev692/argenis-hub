<?php

declare(strict_types=1);

namespace Modules\LeadScout\Tests\Support;

use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Shared\Infrastructure\AI\AIClientInterface;

/**
 * Stands in for the shared AI bridge: canned payloads per agent plus a
 * full record of every prompt — the assertions that prove no PII, no
 * names and no team pages ever reach a provider (spec FR-25).
 */
final class RecordingAiClient implements AIClientInterface
{
    /** @var list<array{agent: string, prompt: string, provider: ?string, model: ?string, timeout: ?int}> */
    public array $calls = [];

    /**
     * @param  array<class-string, array<string, mixed>|list<array<string, mixed>>>  $payloads
     */
    public function __construct(private array $payloads = []) {}

    /**
     * @param  array<class-string, array<string, mixed>|list<array<string, mixed>>>  $payloads
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

        $payload = $this->payloads[$agentClass] ?? throw new RuntimeException("No canned payload for {$agentClass}.");

        if (array_is_list($payload)) {
            $payload = count($payload) > 1 ? array_shift($this->payloads[$agentClass]) : $payload[0];
        }

        return new StructuredAgentResponse(
            invocationId: 'test',
            structured: $payload,
            text: '',
            usage: new Usage(promptTokens: 8000, completionTokens: 500),
            meta: new Meta(provider: (string) $provider, model: (string) $model),
        );
    }

    public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
    {
        return ['base64' => '', 'mime' => 'image/png'];
    }
}
