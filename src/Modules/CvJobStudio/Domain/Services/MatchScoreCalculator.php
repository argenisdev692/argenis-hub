<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\Services;

use Modules\CvJobStudio\Domain\ValueObjects\ScoreBreakdown;

/**
 * The blend (FR-19): total = 0.45·H + 0.25·S + 0.30·D by profile weights,
 * then the cap ladder, then one rounding. Deterministic: same CV, posting
 * and rules version produce the identical total (NFR-1). The LLM never
 * contributes a number — only the stored breakdown feeds this calculator.
 */
final readonly class MatchScoreCalculator
{
    public function __construct(
        private HeuristicSkillScorer $heuristic,
        private SemanticScorer $semantic,
        private DeterministicScorer $deterministic,
        private CapLadder $caps,
        private BandClassifier $bands,
    ) {}

    /**
     * @param  array<int, array{requirement: string, weight: float, evidence: string, position_ratio: float}>  $skillRows
     * @param  array{title_cosine: float, responsibility_cosine: float}  $similarities
     * @param  array<string, float|null>  $signals
     * @param  array{readable: bool, credential_ok: bool, evidence_ok: bool}  $capContext
     * @param  array<string, mixed>  $rules  profile rules (weights, caps, bands, factors)
     */
    #[\NoDiscard]
    public function calculate(
        array $skillRows,
        SkillRelationSet $relations,
        array $similarities,
        array $signals,
        array $capContext,
        array $rules,
    ): ScoreBreakdown {
        $h = $this->heuristic->score($skillRows, $relations, (float) $rules['context_list_factor'], (float) $rules['position_low_factor']);

        $semantic = $rules['semantic'];
        $s = $this->semantic->score(
            $similarities['title_cosine'],
            $similarities['responsibility_cosine'],
            (float) $semantic['floor'],
            (float) $semantic['ceil'],
            (float) $semantic['title_weight'],
            (float) $semantic['responsibility_weight'],
        );

        $d = $this->deterministic->score($signals, $rules['deterministic']['weights']);

        $blend = $rules['blend'];
        $raw = (float) $blend['h'] * $h['h'] + (float) $blend['s'] * $s + (float) $blend['d'] * $d['d'];

        $capped = $this->caps->apply($raw, $capContext, $rules['caps']);

        return new ScoreBreakdown(
            h: $h['h'],
            s: $s,
            d: $d['d'],
            rawScore: $raw,
            totalScore: $capped['total'],
            band: $this->bands->classify($capped['total'], $rules['bands']),
            matches: $h['matches'],
            capsApplied: $capped['caps_applied'],
            capReason: $capped['cap_reason'],
            rulesVersion: (int) ($rules['rules_version'] ?? 2),
            denominator: $h['denominator'],
            capContext: $capContext,
            sInputs: $similarities,
            dInputs: ['signals' => $signals, 'readable' => $d['readable'], 'unreadable' => $d['unreadable']],
        );
    }
}
