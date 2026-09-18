<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Freshness factor (CHG-9): max(floor, 2^(−age/half_life)). Unknown age maps
 * to the policy penalty and is flagged, never treated as fresh (US-9).
 */
final readonly class FreshnessDecay
{
    #[\NoDiscard]
    public function factor(?float $ageDays, float $halfLifeDays, float $floor, float $unknownPenalty): float
    {
        if ($ageDays === null) {
            return $unknownPenalty;
        }

        return max($floor, 2 ** (-max(0.0, $ageDays) / $halfLifeDays));
    }
}
