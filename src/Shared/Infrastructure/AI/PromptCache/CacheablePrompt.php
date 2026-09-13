<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI\PromptCache;

/**
 * A prompt split by how often its parts change, most stable first:
 *
 *   agent instructions (constant) → layers (stable, in order) → tail (per call)
 *
 * Providers cache by exact prefix, so layers must be rendered deterministically
 * (no timestamps, ids or unordered JSON) and reused verbatim across the calls
 * that share them. Longer-lived layers must come before shorter-lived ones.
 */
final readonly class CacheablePrompt
{
    /**
     * @param  list<PromptLayer>  $layers
     */
    public function __construct(
        public array $layers,
        /** The only part that differs between calls. */
        public string $tail,
        /** Stable routing key for providers that take one (OpenAI `prompt_cache_key`). */
        public string $cacheKey,
    ) {
        $seenShort = false;

        foreach ($layers as $layer) {
            if ($layer->longLived && $seenShort) {
                throw new \InvalidArgumentException('Long-lived prompt layers must precede short-lived ones.');
            }

            $seenShort = $seenShort || ! $layer->longLived;
        }
    }

    public function withTail(string $tail): self
    {
        return clone ($this, ['tail' => $tail]);
    }

    /**
     * @return list<PromptLayer>
     */
    public function nonEmptyLayers(): array
    {
        return array_values(array_filter($this->layers, static fn (PromptLayer $layer): bool => ! $layer->isEmpty()));
    }

    /**
     * The whole prompt as one user message, prefix first — for providers that
     * cache by prefix automatically (OpenAI, Gemini).
     */
    public function asSingleMessage(): string
    {
        return implode("\n\n", [
            ...array_map(static fn (PromptLayer $layer): string => $layer->text, $this->nonEmptyLayers()),
            ...(trim($this->tail) === '' ? [] : [$this->tail]),
        ]);
    }
}
