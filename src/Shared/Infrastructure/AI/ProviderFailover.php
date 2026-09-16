<?php

declare(strict_types=1);

namespace Shared\Infrastructure\AI;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Ordered provider attempts for writer calls (suggest + generate).
 *
 * The caller's preferred provider goes first; every provider from
 * `ai.failover_order` follows, de-duplicated. Adapters iterate the list and
 * move on after a retryable failure, so one down provider degrades to the
 * next instead of failing the whole quality-loop iteration.
 *
 * The evaluation judge is deliberately OUTSIDE this mechanism: it must stay
 * on a different provider than the writer to remain independent, so the
 * evaluator adapters keep their single fixed provider.
 */
final readonly class ProviderFailover
{
    public function __construct(private Config $config) {}

    /**
     * @return list<string>
     */
    #[\NoDiscard]
    public function attempts(?string $preferred = null): array
    {
        $ordered = array_values(array_filter(array_map(
            static fn (string $provider): string => mb_strtolower(trim($provider)),
            explode(',', (string) $this->config->get('ai.failover_order', 'openai,anthropic')),
        )));

        $preferred = $preferred !== null && trim($preferred) !== ''
            ? mb_strtolower(trim($preferred))
            : null;

        $attempts = array_values(array_unique([...($preferred !== null ? [$preferred] : []), ...$ordered]));

        return $attempts === [] ? ['openai'] : $attempts;
    }
}
