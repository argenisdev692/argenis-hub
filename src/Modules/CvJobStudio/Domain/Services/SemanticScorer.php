<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * S — semantic component (FR-17, CHG-8 U-2): S = 100·(0.4·S_title + 0.6·S_resp).
 *
 * Cosine similarity is NOT a percentage (RK-1): unrelated technical text sits
 * around 0.5–0.7, so a naive (x+1)/2 map compresses every posting into
 * 0.8–0.9 and stops discriminating. `norm()` calibrates instead:
 * clamp((x − floor) / (ceil − floor)), with floor/ceil from the profile rules
 * (provisional 0.30/0.85 until T-094 calibrates on the held-out set).
 */
final readonly class SemanticScorer
{
    #[\NoDiscard]
    public function normalize(float $cosine, float $floor, float $ceil): float
    {
        if ($ceil <= $floor) {
            return 0.0;
        }

        return min(1.0, max(0.0, ($cosine - $floor) / ($ceil - $floor)));
    }

    #[\NoDiscard]
    public function score(float $titleCosine, float $responsibilityCosine, float $floor, float $ceil, float $titleWeight, float $responsibilityWeight): float
    {
        $title = $this->normalize($titleCosine, $floor, $ceil);
        $resp = $this->normalize($responsibilityCosine, $floor, $ceil);

        return 100.0 * ($titleWeight * $title + $responsibilityWeight * $resp);
    }
}
