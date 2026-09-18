<?php

declare(strict_types=1);

namespace Modules\LeadScout\Domain\Services;

use Modules\LeadScout\Domain\Enums\DecisionOutcome;

/**
 * Decision-rule evaluation (spec US-6 CA-3/CA-4, FR-33, T067): locked
 * sample + thresholds → branch, or «not conclusive» below the sample.
 * Small samples never read as failure — the expected 2-5% band travels
 * with every rate (FR-33).
 */
final readonly class DecisionRuleEvaluator
{
    /**
     * @param  array{sample_size: int, window_days: int, thresholds: array{scale_at: float, stop_below: float}}  $rule
     * @return array{outcome: DecisionOutcome, contacted: int, positives: int, rate: float, expected_low: float, expected_high: float, conclusive: bool, message: string}
     */
    #[\NoDiscard]
    public function evaluate(array $rule, int $contacted, int $positives): array
    {
        $rate = $contacted > 0 ? $positives / $contacted : 0.0;
        $expectedLow = round($contacted * 0.02, 1);
        $expectedHigh = round($contacted * 0.05, 1);
        $conclusive = $contacted >= $rule['sample_size'];

        if (! $conclusive) {
            return [
                'outcome' => DecisionOutcome::Inconclusive,
                'contacted' => $contacted,
                'positives' => $positives,
                'rate' => round($rate, 4),
                'expected_low' => $expectedLow,
                'expected_high' => $expectedHigh,
                'conclusive' => false,
                'message' => "{$positives} de {$contacted}: esperado {$expectedLow}-{$expectedHigh} → no concluyente",
            ];
        }

        $thresholds = $rule['thresholds'];

        $outcome = match (true) {
            $rate >= $thresholds['scale_at'] => DecisionOutcome::Scale,
            $rate < $thresholds['stop_below'] => DecisionOutcome::Stop,
            default => DecisionOutcome::Iterate,
        };

        return [
            'outcome' => $outcome,
            'contacted' => $contacted,
            'positives' => $positives,
            'rate' => round($rate, 4),
            'expected_low' => $expectedLow,
            'expected_high' => $expectedHigh,
            'conclusive' => true,
            'message' => "{$positives} de {$contacted} ({$this->percent($rate)}): {$outcome->value}",
        ];
    }

    private function percent(float $rate): string
    {
        return number_format($rate * 100, 1).'%';
    }
}
