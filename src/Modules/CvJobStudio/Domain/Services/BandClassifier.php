<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

use Modules\CvJobStudio\Domain\Enums\ScoreBand;

/**
 * Action bands 85 / 75 / 70 / 60 (FR-21). Only the recommended bands surface
 * in the apply list; the rest are retained for market statistics.
 */
final readonly class BandClassifier
{
    /**
     * @param  list<array{min: float, band: string}>  $bands  descending by min
     */
    #[\NoDiscard]
    public function classify(float $total, array $bands): ScoreBand
    {
        foreach ($bands as $band) {
            if ($total >= $band['min']) {
                return ScoreBand::from($band['band']);
            }
        }

        return ScoreBand::Skip;
    }
}
