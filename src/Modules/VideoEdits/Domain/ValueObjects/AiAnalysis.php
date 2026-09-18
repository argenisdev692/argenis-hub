<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Domain\ValueObjects;

/**
 * What the AI returned, split along the line that decides behaviour (R6):
 * proposals that a machine may apply, and recommendations that only a person
 * should act on.
 */
final readonly class AiAnalysis
{
    /**
     * @param  list<AiCutProposal>  $cutProposals
     * @param  list<AiRecommendation>  $recommendations
     */
    public function __construct(
        public array $cutProposals = [],
        public array $recommendations = [],
        public ?string $conclusion = null,
    ) {}

    /**
     * Adds a recommendation measured outside the model (the pace analysis).
     */
    #[\NoDiscard]
    public function withRecommendation(AiRecommendation $recommendation): self
    {
        return clone ($this, ['recommendations' => [...$this->recommendations, $recommendation]]);
    }

    public function isEmpty(): bool
    {
        return $this->cutProposals === [] && $this->recommendations === [];
    }
}
