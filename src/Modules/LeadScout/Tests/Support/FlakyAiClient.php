<?php

declare(strict_types=1);

namespace Modules\LeadScout\Tests\Support;

use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Shared\Infrastructure\AI\AIClientInterface;

/**
 * Fails the first structured call, then delegates to an inner client —
 * proving the provider fallback path (spec FR-22) without real providers.
 */
final class FlakyAiClient implements AIClientInterface
{
    public int $calls = 0;

    public function __construct(private readonly RecordingAiClient $inner) {}

    public function generateStructured(string $agentClass, string $prompt, ?string $provider = null, ?string $model = null, ?int $timeoutSeconds = null): StructuredAgentResponse
    {
        $this->calls++;

        if ($this->calls === 1) {
            throw new RuntimeException('Primary is down.');
        }

        return $this->inner->generateStructured($agentClass, $prompt, $provider, $model, $timeoutSeconds);
    }

    public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
    {
        return $this->inner->generateImage($prompt, $provider, $size, $quality);
    }
}
