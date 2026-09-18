<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Shortlist score (FR-13): D_disc = 0.35P + 0.25K + 0.20R + 0.10F + 0.10N.
 * A budget-rationing device — it runs only over gate-passed postings that
 * still lack full text, so extraction spend stays bounded.
 */
final readonly class DiscoveryScorer
{
    /**
     * @param  array{p: float, k: float, r: float, f: float, n: float}  $components  each 0..1
     * @param  array<string, float>  $weights
     */
    #[\NoDiscard]
    public function score(array $components, array $weights): float
    {
        return $weights['p'] * $components['p']
            + $weights['k'] * $components['k']
            + $weights['r'] * $components['r']
            + $weights['f'] * $components['f']
            + $weights['n'] * $components['n'];
    }
}
