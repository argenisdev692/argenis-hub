<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Reach table (T-083, FR-27, SC-10): prices each cap in postings unlocked,
 * computed from stored uncapped values — which is why scores keep `raw_score`
 * beside `total_score`.
 */
final readonly class ReachCalculator
{
    /**
     * @param  array<int, array{cap_reason: string|null, raw_score: float, total_score: float}>  $scores
     * @param  float  $threshold  fit threshold that decides list entry
     * @return array<int, array{cap: string, capped_count: int, unlocked_count: int}>
     */
    #[\NoDiscard]
    public function table(array $scores, float $threshold): array
    {
        $byCap = [];

        foreach ($scores as $score) {
            if ($score['cap_reason'] === null) {
                continue;
            }

            $byCap[$score['cap_reason']][] = $score;
        }

        $rows = [];

        foreach ($byCap as $cap => $capped) {
            $unlocked = 0;

            foreach ($capped as $score) {
                if ($score['total_score'] < $threshold && $score['raw_score'] >= $threshold) {
                    $unlocked++;
                }
            }

            $rows[] = ['cap' => $cap, 'capped_count' => count($capped), 'unlocked_count' => $unlocked];
        }

        usort($rows, static fn (array $a, array $b): int => $b['unlocked_count'] <=> $a['unlocked_count']);

        return $rows;
    }
}
