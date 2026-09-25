<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Per-run requirement frequency (T-082, FR-26): how many of the postings read
 * asked for each thing. Percentages only once the sample supports them.
 *
 * The input is one row per (posting, requirement) pair, so `count` is "6 of 16
 * jobs want Laravel" — the number a candidate can act on. A deduplicated name
 * list would make every count 1 and the report worthless.
 *
 * Grouping runs through {@see RequirementCanonicalizer} rather than the raw
 * `canonical_name`, because a single real signal arrives spelled half a dozen
 * ways: "English Communication Skills", "English communication skills" and
 * "English Proficiency" are one requirement, and counting them separately
 * understates exactly the demand the reader needs to act on. Eligibility and
 * logistics clauses are counted apart, never mixed into the skill table.
 */
final readonly class RequirementFrequencyCounter
{
    /**
     * @param  array<int, array{posting_id: int, canonical_name: string, covered: bool}>  $requirements
     * @return array{total: int, requirements: array<int, array{name: string, key: string, count: int, covered: bool, percentage: float|null}>, eligibility: array<string, int>}
     */
    #[\NoDiscard]
    public function count(array $requirements, int $minimumForPercentage): array
    {
        $canonicalizer = new RequirementCanonicalizer;

        /** @var array<string, array{key: string, label: string, postings: array<int, true>, covered: bool}> $seen */
        $seen = [];

        /** @var array<string, array<int, true>> $eligibility */
        $eligibility = [];

        $postingIds = [];

        foreach ($requirements as $requirement) {
            $postingId = $requirement['posting_id'];
            $postingIds[$postingId] = true;

            $name = $requirement['canonical_name'];

            if ($canonicalizer->isEligibility($name)) {
                $eligibility[$name][$postingId] = true;

                continue;
            }

            $key = $canonicalizer->key($name);

            // Noise: not a skill, not eligibility. Counted nowhere — it would
            // only re-flood the table with "Reliable internet access".
            if ($key === null) {
                continue;
            }

            $seen[$key] ??= [
                'key' => $key,
                'label' => $canonicalizer->label($key),
                'postings' => [],
                'covered' => false,
            ];

            $seen[$key]['postings'][$postingId] = true;
            $seen[$key]['covered'] = $seen[$key]['covered'] || $requirement['covered'];
        }

        $total = count($postingIds);
        $rows = [];

        foreach ($seen as $entry) {
            $count = count($entry['postings']);

            $rows[] = [
                'name' => $entry['label'],
                'key' => $entry['key'],
                'count' => $count,
                'covered' => $entry['covered'],
                'percentage' => $total >= $minimumForPercentage && $total > 0
                    ? round(100.0 * $count / $total, 1)
                    : null,
            ];
        }

        // Frequency first, then alphabetical — a stable order so the same
        // corpus always renders the same table (NFR-1).
        usort($rows, static function (array $a, array $b): int {
            return [$b['count'], $a['name']] <=> [$a['count'], $b['name']];
        });

        $eligibilityCounts = array_map(
            static fn (array $postings): int => count($postings),
            $eligibility,
        );

        // Count desc, then alphabetical: `arsort` alone is not stable for equal
        // counts, and the same corpus must always render the same table (NFR-1).
        uksort(
            $eligibilityCounts,
            static fn (string $a, string $b): int => [$eligibilityCounts[$b], $a] <=> [$eligibilityCounts[$a], $b],
        );

        return [
            'total' => $total,
            'requirements' => $rows,
            'eligibility' => $eligibilityCounts,
        ];
    }
}
