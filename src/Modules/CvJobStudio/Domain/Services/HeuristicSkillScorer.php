<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * H — heuristic skill match (FR-16, CHG-8 U-1).
 *
 * H = 100 · Σ w·c·κ·π / (W_hard + W_soft), with κ (context: list-only
 * evidence counts less) and π (position: evidence below the top third counts
 * less) both bounded at 1.0 — they can only reduce a credit, never inflate
 * it. With κ = π = 1 this reproduces rules v1 exactly (monotone upgrade).
 * Zero denominator yields 50 with a flag, per the local pipeline.
 */
final readonly class HeuristicSkillScorer
{
    /**
     * @param  array<int, array{requirement: string, weight: float, evidence: string, position_ratio: float}>  $rows
     * @return array{h: float, denominator: float, zero_denominator: bool, matches: array<int, array{requirement: string, weight: float, credit_base: float, context_factor: float, position_factor: float, credit_final: float}>}
     */
    #[\NoDiscard]
    public function score(array $rows, SkillRelationSet $relations, float $contextListFactor, float $positionLowFactor): array
    {
        $denominator = 0.0;
        $numerator = 0.0;
        $matches = [];

        foreach ($rows as $row) {
            $creditBase = $relations->creditFor($row['requirement']);
            $contextFactor = $row['evidence'] === 'list_only' ? $contextListFactor : 1.0;
            $positionFactor = $row['position_ratio'] <= 1 / 3 ? 1.0 : $positionLowFactor;
            $creditFinal = $row['weight'] * $creditBase * $contextFactor * $positionFactor;

            $denominator += $row['weight'];
            $numerator += $creditFinal;

            $matches[] = [
                'requirement' => $row['requirement'],
                'weight' => $row['weight'],
                'credit_base' => $creditBase,
                'context_factor' => $contextFactor,
                'position_factor' => $positionFactor,
                'credit_final' => $creditFinal,
            ];
        }

        if ($denominator <= 0.0) {
            return ['h' => 50.0, 'denominator' => 0.0, 'zero_denominator' => true, 'matches' => $matches];
        }

        return ['h' => 100.0 * $numerator / $denominator, 'denominator' => $denominator, 'zero_denominator' => false, 'matches' => $matches];
    }
}
