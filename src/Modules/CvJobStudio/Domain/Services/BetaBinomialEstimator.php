<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Evidence gate (CHG-16, FR-48): below 30 applications or 3 positives the
 * policy value holds and the own rate shows with its 90% credible interval,
 * flagged "not applied". Above the gate, outcomes blend with weight N0 = 100
 * so few outcomes move a factor little. Never touches fit (NFR-13).
 */
final readonly class BetaBinomialEstimator
{
    /**
     * @return array{applied: bool, value: float, lower: float, upper: float}
     */
    #[\NoDiscard]
    public function estimate(float $policyValue, int $positives, int $trials, int $minTrials, int $minPositives, float $priorStrength): array
    {
        $interval = $this->credibleInterval($positives, $trials);

        if ($trials < $minTrials || $positives < $minPositives) {
            return ['applied' => false, 'value' => $policyValue, 'lower' => $interval[0], 'upper' => $interval[1]];
        }

        $blended = ($priorStrength * $policyValue + $positives) / ($priorStrength + $trials);

        return ['applied' => true, 'value' => $blended, 'lower' => $interval[0], 'upper' => $interval[1]];
    }

    /** @return array{0: float, 1: float} */
    private function credibleInterval(int $positives, int $trials): array
    {
        if ($trials === 0) {
            return [0.0, 1.0];
        }

        $rate = $positives / $trials;
        $margin = 1.645 * sqrt(max($rate * (1 - $rate), 0.0) / $trials);

        return [max(0.0, $rate - $margin), min(1.0, $rate + $margin)];
    }
}
