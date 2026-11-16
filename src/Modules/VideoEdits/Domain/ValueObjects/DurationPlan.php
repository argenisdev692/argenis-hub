<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * The arithmetic of a target: where a recording stands, how much time has to
 * go, and where that time can come from.
 *
 * This exists because the model was being asked to "reduce to 10 minutes" with
 * no idea what that meant. A number in a prompt is a wish; `13:42 → 10:00`
 * needs `3:42` removed, and the recording only contains so much dead air. The
 * plan states the shortfall instead of letting the owner approve a list of cuts
 * that lands at 11:20 and calls it done.
 *
 * **Everything here is measured, never estimated.** Gaps come from the word
 * timings the transcriber already produced; `silenceRecoverableMs` is what the
 * configured silence threshold will actually take; `aiProposalMs` is the
 * duration of the word ranges the analyzer proposed. The two are added to
 * project a result, and they are counted separately on purpose: a gap is dead
 * air the recorder left, a proposal is speech somebody decided was wrong.
 *
 * **The projection is deliberately conservative.** Speech cleanup (fillers,
 * stutters, "pausa") also removes time and is not counted here — those
 * decisions do not exist yet when the analysis runs. So a `short` verdict means
 * "at least this much is still missing", never "the target is unreachable by
 * that amount". That is the direction an estimate should err in.
 */
final readonly class DurationPlan
{
    /** No target was asked for; the plan is a gap inventory and nothing more. */
    public const string VERDICT_NO_TARGET = 'no_target';

    /** The recording is already at or under the target. */
    public const string VERDICT_TARGET_MET = 'target_met';

    /** The measured removals reach the target. */
    public const string VERDICT_REACHABLE = 'reachable';

    /** Measured removals do not reach the target; `shortfallMs` says by how much. */
    public const string VERDICT_SHORT = 'short';

    /**
     * @param  list<array{start_ms: int, end_ms: int, gap_ms: int, after_sentence_end: bool}>  $longestGaps  longest first, capped by config
     */
    public function __construct(
        public int $totalMs,
        public ?int $targetMs,
        public int $gapCount,
        public int $deadAirMs,
        public int $sentenceGapMs,
        public int $intraPhraseGapMs,
        public int $silenceRecoverableMs,
        public int $aiProposalMs,
        public array $longestGaps = [],
        /**
         * Deterministic speech cleanup (fillers, stutters, repetitions, the
         * spoken marker, robotic gaps). Counted separately from the AI's
         * proposals because the two are reviewed under different evidence, and
         * leaving it out is what made the first version of this plan understate
         * the edit by minutes: most of the removed time in a real recording is
         * these cuts, not the retakes.
         */
        public int $speechCutMs = 0,
        public int $manualCutMs = 0,
        /**
         * Speech, manual and AI ranges merged together: the time the edit
         * actually removes. The per-origin fields above are the breakdown shown
         * to a person; they can sum slightly higher when one cut sits inside
         * another, which is why the projection uses this one and not their sum.
         */
        public int $plannedCutMs = 0,
    ) {}

    /**
     * What has to disappear to hit the target, or null when no target was set.
     */
    #[\NoDiscard]
    public function requiredRemovalMs(): ?int
    {
        return $this->targetMs === null ? null : max(0, $this->totalMs - $this->targetMs);
    }

    /**
     * The removals this analysis can point at today — every origin, because a
     * removed second is a removed second whatever proposed it.
     */
    #[\NoDiscard]
    public function plannedRemovalMs(): int
    {
        return min($this->totalMs, $this->silenceRecoverableMs + $this->plannedCutMs);
    }

    /**
     * Where the video lands if every measured removal is applied.
     */
    #[\NoDiscard]
    public function projectedMs(): int
    {
        return max(0, $this->totalMs - $this->plannedRemovalMs());
    }

    /**
     * How much is still missing after the measured removals, or null without a
     * target.
     */
    #[\NoDiscard]
    public function shortfallMs(): ?int
    {
        $required = $this->requiredRemovalMs();

        return $required === null ? null : max(0, $required - $this->plannedRemovalMs());
    }

    /**
     * @return self::VERDICT_*
     */
    #[\NoDiscard]
    public function verdict(): string
    {
        if ($this->targetMs === null) {
            return self::VERDICT_NO_TARGET;
        }

        if ($this->requiredRemovalMs() === 0) {
            return self::VERDICT_TARGET_MET;
        }

        return $this->shortfallMs() === 0 ? self::VERDICT_REACHABLE : self::VERDICT_SHORT;
    }

    #[\NoDiscard]
    public function meetsTarget(): bool
    {
        return $this->verdict() === self::VERDICT_TARGET_MET
            || $this->verdict() === self::VERDICT_REACHABLE;
    }

    /**
     * @return array{
     *     total_ms: int,
     *     target_ms: int|null,
     *     required_removal_ms: int|null,
     *     planned_removal_ms: int,
     *     projected_ms: int,
     *     shortfall_ms: int|null,
     *     verdict: string,
     *     gap_count: int,
     *     dead_air_ms: int,
     *     sentence_gap_ms: int,
     *     intra_phrase_gap_ms: int,
     *     silence_recoverable_ms: int,
     *     ai_proposal_ms: int,
     *     speech_cut_ms: int,
     *     manual_cut_ms: int,
     *     longest_gaps: list<array{start_ms: int, end_ms: int, gap_ms: int, after_sentence_end: bool}>
     * }
     */
    #[\NoDiscard]
    public function toArray(): array
    {
        return [
            'total_ms' => $this->totalMs,
            'target_ms' => $this->targetMs,
            'required_removal_ms' => $this->requiredRemovalMs(),
            'planned_removal_ms' => $this->plannedRemovalMs(),
            'projected_ms' => $this->projectedMs(),
            'shortfall_ms' => $this->shortfallMs(),
            'verdict' => $this->verdict(),
            'gap_count' => $this->gapCount,
            'dead_air_ms' => $this->deadAirMs,
            'sentence_gap_ms' => $this->sentenceGapMs,
            'intra_phrase_gap_ms' => $this->intraPhraseGapMs,
            'silence_recoverable_ms' => $this->silenceRecoverableMs,
            'ai_proposal_ms' => $this->aiProposalMs,
            'speech_cut_ms' => $this->speechCutMs,
            'manual_cut_ms' => $this->manualCutMs,
            'planned_cut_ms' => $this->plannedCutMs,
            'longest_gaps' => $this->longestGaps,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    #[\NoDiscard]
    public static function fromArray(array $row): ?self
    {
        $totalMs = $row['total_ms'] ?? null;

        if (! is_int($totalMs) && ! is_numeric($totalMs)) {
            return null;
        }

        $gaps = [];

        foreach (is_array($row['longest_gaps'] ?? null) ? $row['longest_gaps'] : [] as $gap) {
            if (! is_array($gap) || ! isset($gap['start_ms'], $gap['end_ms'], $gap['gap_ms'])) {
                continue;
            }

            $gaps[] = [
                'start_ms' => (int) $gap['start_ms'],
                'end_ms' => (int) $gap['end_ms'],
                'gap_ms' => (int) $gap['gap_ms'],
                'after_sentence_end' => (bool) ($gap['after_sentence_end'] ?? false),
            ];
        }

        $targetMs = $row['target_ms'] ?? null;

        return new self(
            totalMs: (int) $totalMs,
            targetMs: is_numeric($targetMs) ? (int) $targetMs : null,
            gapCount: (int) ($row['gap_count'] ?? 0),
            deadAirMs: (int) ($row['dead_air_ms'] ?? 0),
            sentenceGapMs: (int) ($row['sentence_gap_ms'] ?? 0),
            intraPhraseGapMs: (int) ($row['intra_phrase_gap_ms'] ?? 0),
            silenceRecoverableMs: (int) ($row['silence_recoverable_ms'] ?? 0),
            aiProposalMs: (int) ($row['ai_proposal_ms'] ?? 0),
            longestGaps: $gaps,
            speechCutMs: (int) ($row['speech_cut_ms'] ?? 0),
            manualCutMs: (int) ($row['manual_cut_ms'] ?? 0),
            plannedCutMs: (int) ($row['planned_cut_ms'] ?? 0),
        );
    }
}
