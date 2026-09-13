<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Tests\Support;

use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Shared\Infrastructure\AI\AIClientInterface;

/**
 * Stands in for the shared AI bridge: returns canned payloads per agent and
 * records exactly what would have been sent — the prompt and the provider
 * options the agent emits at call time (prompt-cache fields).
 */
final class RecordingAiClient implements AIClientInterface
{
    /** @var list<array{agent: string, prompt: string, provider: ?string, options: array<string, mixed>}> */
    public array $calls = [];

    /**
     * @param  array<class-string, array<string, mixed>|list<array<string, mixed>>>  $payloads  one payload, or a list consumed in order
     */
    public function __construct(private array $payloads = []) {}

    public static function install(array $payloads = []): self
    {
        $client = new self($payloads);
        app()->instance(AIClientInterface::class, $client);

        return $client;
    }

    public function generateStructured(string $agentClass, string $prompt, ?string $provider = null, ?string $model = null, ?int $timeoutSeconds = null): StructuredAgentResponse
    {
        $agent = app($agentClass);

        $this->calls[] = [
            'agent' => $agentClass,
            'prompt' => $prompt,
            'provider' => $provider,
            'options' => $agent instanceof HasProviderOptions ? $agent->providerOptions((string) $provider) : [],
        ];

        $payload = $this->payloads[$agentClass] ?? throw new RuntimeException("No canned payload for {$agentClass}.");

        if (array_is_list($payload)) {
            $payload = count($payload) > 1 ? array_shift($this->payloads[$agentClass]) : $payload[0];
        }

        return new StructuredAgentResponse(
            invocationId: 'test',
            structured: $payload,
            text: '',
            usage: new Usage,
            meta: new Meta(provider: (string) $provider, model: 'test'),
        );
    }

    public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
    {
        return ['base64' => '', 'mime' => 'image/png'];
    }
}
