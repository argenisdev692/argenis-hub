<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Query-experiment metric (T-030, CHG-5): relevant-and-deduplicated yield
 * per euro, recorded per (portal × template × locale) cell. Raw result
 * counts never enter the metric — only gate-passing, deduplicated postings
 * that score ≥ 70.
 */
final readonly class ExperimentMetricCalculator
{
    /**
     * @param  array{candidates: int, gate_pass_rate: float, unique_after_dedup: int, scored_gte_70: int, cost_micros: int}  $experiment
     * @return array{yield_per_euro: float|null, relevant_share: float}
     */
    #[\NoDiscard]
    public function metric(array $experiment): array
    {
        $relevantShare = $experiment['candidates'] > 0
            ? $experiment['scored_gte_70'] / $experiment['candidates']
            : 0.0;

        return [
            'yield_per_euro' => $experiment['cost_micros'] > 0
                ? $experiment['unique_after_dedup'] / ($experiment['cost_micros'] / 1_000_000)
                : null,
            'relevant_share' => $relevantShare,
        ];
    }
}
