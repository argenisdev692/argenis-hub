<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * D — deterministic component (FR-18): experience / location clarity /
 * education / language, renormalised over the signals actually readable.
 * An unreadable signal drops out of both numerator and denominator instead
 * of scoring zero.
 */
final readonly class DeterministicScorer
{
    /**
     * @param  array<string, float|null>  $signals  e.g. ['experience' => 95, 'location' => null, ...]
     * @param  array<string, float>  $weights  e.g. ['experience' => 40, ...]
     * @return array{d: float, readable: list<string>, unreadable: list<string>}
     */
    #[\NoDiscard]
    public function score(array $signals, array $weights): array
    {
        $numerator = 0.0;
        $denominator = 0.0;
        $readable = [];
        $unreadable = [];

        foreach ($weights as $signal => $weight) {
            $value = $signals[$signal] ?? null;

            if ($value === null) {
                $unreadable[] = $signal;

                continue;
            }

            $readable[] = $signal;
            $numerator += $weight * min(100.0, max(0.0, $value));
            $denominator += $weight;
        }

        if ($denominator <= 0.0) {
            return ['d' => 50.0, 'readable' => $readable, 'unreadable' => $unreadable];
        }

        return ['d' => $numerator / $denominator, 'readable' => $readable, 'unreadable' => $unreadable];
    }
}
