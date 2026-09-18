<?php

declare(strict_types=1);

namespace Modules\CvJobStudio\Domain\ValueObjects;

use Modules\CvJobStudio\Domain\Enums\ScoreBand;

/**
 * One evaluation of a posting against the CV profile. Carries everything
 * needed to recompute the total offline (SC-2): components, weights, caps,
 * uncapped value and rules version.
 *
 * @phpstan-type MatchRow array{requirement: string, weight: float, credit_base: float, context_factor: float, position_factor: float, credit_final: float}
 */
final readonly class ScoreBreakdown
{
    /**
     * `denominator` + `capContext` + the S/D inputs travel in the components
     * bag so any stored score recomputes offline to the identical total
     * (SC-2, T-063) — no provider, no inputs, just the row.
     *
     * @param  array<int, MatchRow>  $matches
     * @param  list<array{max: float, reason: string}>  $capsApplied
     * @param  array{readable: bool, credential_ok: bool, evidence_ok: bool}  $capContext
     * @param  array<string, mixed>  $sInputs
     * @param  array<string, mixed>  $dInputs
     */
    public function __construct(
        public float $h,
        public float $s,
        public float $d,
        public float $rawScore,
        public float $totalScore,
        public ScoreBand $band,
        public array $matches = [],
        public array $capsApplied = [],
        public ?string $capReason = null,
        public int $rulesVersion = 2,
        public float $denominator = 0.0,
        public array $capContext = ['readable' => true, 'credential_ok' => true, 'evidence_ok' => true],
        public array $sInputs = [],
        public array $dInputs = [],
    ) {}
}
