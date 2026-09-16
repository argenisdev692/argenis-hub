<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Ai;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Per-step model pinning and timeouts for course generation.
 *
 * The writer provider is picked per run; the model never comes from the
 * request (OWASP LLM03). Null model keeps the provider default so existing
 * runs are untouched until an env version is pinned.
 */
final readonly class GenerationRequestPolicy
{
    public function __construct(private Config $config) {}

    #[\NoDiscard]
    public function modelFor(string $step): ?string
    {
        $model = $this->config->get('course-scripts.generation.models.'.$step);

        return is_string($model) && trim($model) !== '' ? $model : null;
    }

    #[\NoDiscard]
    public function timeoutFor(string $step): ?int
    {
        $timeout = (int) $this->config->get('course-scripts.generation.timeouts.'.$step, 0);

        return $timeout > 0 ? $timeout : null;
    }

    /**
     * Ordered fallback providers for a step: configured order minus the
     * primary, capped at two so a run cannot fan out unbounded (LLM10).
     *
     * @return list<string>
     */
    #[\NoDiscard]
    public function fallbacksFor(string $primary): array
    {
        $order = (string) $this->config->get('course-scripts.generation.failover_order', 'openai,anthropic,gemini');

        $fallbacks = array_values(array_filter(
            array_map(trim(...), explode(',', $order)),
            static fn (string $provider): bool => $provider !== '' && strtolower($provider) !== strtolower($primary),
        ));

        return array_slice(array_values(array_unique($fallbacks)), 0, 2);
    }
}
