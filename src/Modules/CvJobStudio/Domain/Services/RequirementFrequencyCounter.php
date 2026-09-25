<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Per-run requirement frequency (T-082, FR-26): counts every requirement
 * across every posting read in a run — including capped and below-threshold
 * ones, excluding stack-fails (G2 never produced requirements). Percentages
 * only once the sample supports them.
 *
 * The input is one row per (posting, requirement) pair, so `count` is how many
 * postings asked for a thing and the percentage is over the postings analysed
 * — "6 of 16 jobs want Laravel", which is the number a candidate can act on. A
 * deduplicated name list would make every count 1 and the report worthless.
 */
final readonly class RequirementFrequencyCounter
{
    /**
     * @param  array<int, array{posting_id: int, canonical_name: string, covered: bool}>  $requirements
     * @return array{total: int, requirements: array<int, array{name: string, count: int, covered: bool, percentage: float|null}>}
     */
    #[\NoDiscard]
    public function count(array $requirements, int $minimumForPercentage): array
    {
        /** @var array<string, array{postings: array<int, true>, covered: bool}> $seen */
        $seen = [];

        foreach ($requirements as $requirement) {
            $name = $requirement['canonical_name'];
            $seen[$name] ??= ['postings' => [], 'covered' => false];
            $seen[$name]['postings'][$requirement['posting_id']] = true;
            $seen[$name]['covered'] = $seen[$name]['covered'] || $requirement['covered'];
        }

        $total = count(array_unique(array_column($requirements, 'posting_id'), SORT_REGULAR));
        $rows = [];

        foreach ($seen as $name => $entry) {
            $count = count($entry['postings']);

            $rows[] = [
                'name' => $name,
                'count' => $count,
                'covered' => $entry['covered'],
                'percentage' => $total >= $minimumForPercentage && $total > 0
                    ? round(100.0 * $count / $total, 1)
                    : null,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$b['count'], $a['name']] <=> [$a['count'], $b['name']];
        });

        return ['total' => $total, 'requirements' => $rows];
    }
}
