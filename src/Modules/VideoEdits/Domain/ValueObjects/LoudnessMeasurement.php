<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * What `loudnorm` measured on a piece of audio.
 *
 * These are the five numbers its second pass needs to apply a **constant**
 * gain instead of its dynamic mode. Dynamic mode decides the gain while it
 * plays, and a slow ramp after silence is exactly the fault the content review
 * reported: the first phrase comes in under level and climbs back over the next
 * few seconds.
 *
 * The values are deliberately kept as a value object rather than an array: the
 * filter string is built from named numbers, so a missing key cannot silently
 * turn into `measured_I=0` (a 70 dB boost) at the edge of the graph.
 */
final readonly class LoudnessMeasurement
{
    public function __construct(
        public float $integratedLufs,
        public float $truePeakDb,
        public float $loudnessRange,
        public float $thresholdLufs,
        public float $targetOffset,
    ) {}
}
