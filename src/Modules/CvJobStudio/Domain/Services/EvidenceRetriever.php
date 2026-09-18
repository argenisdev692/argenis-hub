<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

/**
 * Top-3 bullets per requirement and responsibility from the MaxSim pass
 * (T-123, U-3): feeds the rewriter front-loading and the skills-echoed-in-
 * bullets rule. An embedding hit on an unrelated skill creates a PENDING
 * relation proposal — never credit (FR-44).
 */
final readonly class EvidenceRetriever
{
    /**
     * @param  array<int, array{bullet_id: int, text: string, similarities: array<string, float>}>  $matrix  per-bullet MaxSim rows keyed by requirement name
     * @param  list<string>  $targets  requirement / responsibility names
     * @return array{evidence: array<string, list<array{bullet_id: int, text: string, score: float}>>, proposals: list<array{from: string, to: string, kind: string}>}
     */
    #[\NoDiscard]
    public function retrieve(array $matrix, array $targets, int $topN = 3): array
    {
        $evidence = [];
        $proposals = [];

        foreach ($targets as $target) {
            $ranked = [];

            foreach ($matrix as $row) {
                $score = $row['similarities'][$target] ?? 0.0;

                $ranked[] = ['bullet_id' => $row['bullet_id'], 'text' => $row['text'], 'score' => $score];
            }

            usort($ranked, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

            $evidence[$target] = array_slice($ranked, 0, $topN);
        }

        return ['evidence' => $evidence, 'proposals' => $proposals];
    }

    /**
     * An embedding hit linking two otherwise-unrelated skills proposes a
     * family relation for the inbox — credit stays 0 until confirmed.
     *
     * @return array{from: string, to: string, kind: string, origin: string, status: string}
     */
    #[\NoDiscard]
    public function proposeRelation(string $requirement, string $bulletSkill): array
    {
        return [
            'from' => $requirement,
            'to' => $bulletSkill,
            'kind' => 'family',
            'origin' => 'model',
            'status' => 'pending',
        ];
    }
}
