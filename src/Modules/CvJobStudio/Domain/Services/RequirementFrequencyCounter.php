<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Per-run requirement frequency (T-082, FR-26): counts every requirement
 * across every posting read in a run — including capped and below-threshold
 * ones, excluding stack-fails (G2 never produced requirements). Percentages
 * only once the sample supports them.
 */
final readonly class RequirementFrequencyCounter
{
    /**
     * @param  array<int, array{canonical_name: string, covered: bool}>  $requirements
     * @return array{total: int, requirements: array<int, array{name: string, count: int, covered: bool, percentage: float|null}>}
     */
    #[\NoDiscard]
    public function count(array $requirements, int $minimumForPercentage): array
    {
        $counts = [];
        $covered = [];

        foreach ($requirements as $requirement) {
            $name = $requirement['canonical_name'];
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            $covered[$name] = ($covered[$name] ?? false) || $requirement['covered'];
        }

        $total = count($requirements);
        $rows = [];

        foreach ($counts as $name => $count) {
            $rows[] = [
                'name' => $name,
                'count' => $count,
                'covered' => $covered[$name],
                'percentage' => $total >= $minimumForPercentage ? round(100.0 * $count / $total, 1) : null,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return ['total' => $total, 'requirements' => $rows];
    }
}
