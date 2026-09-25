<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Insight-report recommendations (T-085, FR-8, FR-26): what to lead with, not
 * what to learn.
 *
 * Every candidate is filtered to requirements the CV already evidences, so the
 * report can never tell someone to add a skill they do not have — the one
 * fabrication path a statistical report would otherwise open. What survives is
 * a frequency statement about things the candidate can already evidence, which
 * is the actionable half: a covered requirement that keeps coming back is
 * worth surfacing at the top of the CV.
 *
 * Deterministic by construction (FR-1: same inputs, same output): the input
 * rows arrive already ordered by frequency with a name tie-break, and nothing
 * here reads a clock, a locale or a random source.
 */
final readonly class InsightRecommender
{
    private const int MIN_POSTINGS = 2;

    private const int MAX_RECOMMENDATIONS = 5;

    /**
     * @param  array{total: int, requirements: array<int, array{name: string, count: int, covered: bool, percentage: float|null}>}  $frequency
     * @return list<string>
     */
    #[\NoDiscard]
    public function recommend(array $frequency): array
    {
        $analyzed = $frequency['total'];
        $recommendations = [];

        foreach ($frequency['requirements'] as $requirement) {
            if (! $requirement['covered'] || $requirement['count'] < self::MIN_POSTINGS) {
                continue;
            }

            $recommendations[] = sprintf(
                '%s appears in %d of the %d analysed jobs and your CV already evidences it — keep it in the summary and in the first relevant bullet.',
                $requirement['name'],
                $requirement['count'],
                $analyzed,
            );

            if (count($recommendations) === self::MAX_RECOMMENDATIONS) {
                break;
            }
        }

        return $recommendations;
    }
}
