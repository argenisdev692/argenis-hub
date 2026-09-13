<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\FailoverableException;
use Laravel\Ai\Image;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Shared\Infrastructure\Resilience\CircuitBreaker\CircuitBreakerInterface;
use Throwable;

/**
 * @see AIClientInterface
 */
final readonly class LaravelAIAdapter implements AIClientInterface
{
    private const int DEFAULT_TIMEOUT_SECONDS = 60;

    public function __construct(private CircuitBreakerInterface $breaker) {}

    public function generateStructured(
        string $agentClass,
        string $prompt,
        ?string $provider = null,
        ?string $model = null,
        ?int $timeoutSeconds = null,
    ): StructuredAgentResponse {
        return $this->breaker->call(
            'ai:structured:'.($provider ?? 'default'),
            function () use ($agentClass, $prompt, $provider, $model, $timeoutSeconds) {
                try {
                    $response = app($agentClass)->prompt(
                        $prompt,
                        provider: $provider,
                        model: $model,
                        timeout: $timeoutSeconds ?? self::DEFAULT_TIMEOUT_SECONDS,
                    );

                    if (! $response instanceof StructuredAgentResponse) {
                        throw new \UnexpectedValueException(sprintf(
                            'Expected %s from structured agent %s, received %s.',
                            StructuredAgentResponse::class,
                            $agentClass,
                            $response::class,
                        ));
                    }

                    return $response;
                } catch (FailoverableException|Throwable $e) {
                    Log::error('ai.structured_generation_failed', [
                        'agent' => $agentClass,
                        'provider' => $provider,
                        // Provider errors can echo the prompt back (user material,
                        // LLM02), so only a bounded prefix reaches the log.
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ]);

                    throw $e;
                }
            },
        );
    }

    public function generateImage(string $prompt, ?string $provider = null, string $size = '1:1', string $quality = 'high'): array
    {
        return $this->breaker->call(
            'ai:image:'.($provider ?? 'default'),
            function () use ($prompt, $provider, $size, $quality): array {
                try {
                    $image = Image::of($prompt)
                        ->size($size)
                        ->quality($quality)
                        ->generate(provider: $provider)
                        ->firstImage();

                    return [
                        'base64' => $image->image,
                        'mime' => $image->mime(),
                    ];
                } catch (FailoverableException|Throwable $e) {
                    Log::error('ai.image_generation_failed', [
                        'provider' => $provider,
                        // Provider errors can echo the prompt back (user material,
                        // LLM02), so only a bounded prefix reaches the log.
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ]);

                    throw $e;
                }
            },
        );
    }
}
