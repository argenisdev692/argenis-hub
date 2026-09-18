<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Directional requirement-frequency comparison between answered and silent
 * applications (T-084, FR-28). Suppressed below 8 usable outcomes; labelled
 * directional, never causal (NFR-3).
 */
final readonly class OutcomeCorrelator
{
    /**
     * @param  array<int, array{requirement: string, answered: bool}>  $observations
     * @return array{suppressed: bool, rows: array<int, array{requirement: string, answered_rate: float|null, silent_rate: float|null}>}
     */
    #[\NoDiscard]
    public function compare(array $observations, int $minimumOutcomes): array
    {
        $answered = [];
        $silent = [];
        $answeredTotal = 0;
        $silentTotal = 0;

        foreach ($observations as $observation) {
            if ($observation['answered']) {
                $answered[$observation['requirement']] = ($answered[$observation['requirement']] ?? 0) + 1;
                $answeredTotal++;
            } else {
                $silent[$observation['requirement']] = ($silent[$observation['requirement']] ?? 0) + 1;
                $silentTotal++;
            }
        }

        if ($answeredTotal + $silentTotal < $minimumOutcomes) {
            return ['suppressed' => true, 'rows' => []];
        }

        $names = array_unique([...array_keys($answered), ...array_keys($silent)]);
        $rows = [];

        foreach ($names as $name) {
            $rows[] = [
                'requirement' => $name,
                'answered_rate' => $answeredTotal > 0 ? round(($answered[$name] ?? 0) / $answeredTotal, 3) : null,
                'silent_rate' => $silentTotal > 0 ? round(($silent[$name] ?? 0) / $silentTotal, 3) : null,
            ];
        }

        return ['suppressed' => false, 'rows' => $rows];
    }
}
