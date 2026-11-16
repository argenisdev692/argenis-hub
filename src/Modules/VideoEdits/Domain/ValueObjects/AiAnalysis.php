<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * What the AI returned, split along the line that decides behaviour (R6):
 * proposals that a machine may apply, and recommendations that only a person
 * should act on.
 *
 * Two measured additions ride along, and neither was written by the model:
 * `durationPlan` is the target arithmetic computed from the word timings, and
 * `keptPhrases` are the owner's never-cut phrases that blocked a proposal. They
 * travel with the analysis so the report can show them next to the model's own
 * findings, and so a second stored copy cannot drift.
 */
final readonly class AiAnalysis
{
    /**
     * @param  list<AiCutProposal>  $cutProposals
     * @param  list<AiRecommendation>  $recommendations
     * @param  list<string>  $keptPhrases  phrases that suppressed at least one proposal
     */
    public function __construct(
        public array $cutProposals = [],
        public array $recommendations = [],
        public ?string $conclusion = null,
        public ?DurationPlan $durationPlan = null,
        public array $keptPhrases = [],
    ) {}

    /**
     * Adds a recommendation measured outside the model (the pace analysis).
     */
    #[\NoDiscard]
    public function withRecommendation(AiRecommendation $recommendation): self
    {
        return clone ($this, ['recommendations' => [...$this->recommendations, $recommendation]]);
    }

    /**
     * @param  list<AiCutProposal>  $cutProposals
     */
    #[\NoDiscard]
    public function withCutProposals(array $cutProposals): self
    {
        return clone ($this, ['cutProposals' => $cutProposals]);
    }

    #[\NoDiscard]
    public function withKeptPhrases(array $keptPhrases): self
    {
        return clone ($this, ['keptPhrases' => array_values(array_unique($keptPhrases))]);
    }

    public function isEmpty(): bool
    {
        return $this->cutProposals === [] && $this->recommendations === [];
    }
}
