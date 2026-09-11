<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\Pipeline;

use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;

/**
 * Resolves which producers take part in an edit (EX-2).
 *
 * Producers are container-tagged with {@see self::TAG}. V2 speech detectors and
 * the V3 AI analyzer join by being tagged — nothing downstream of this registry
 * (validation, planning, rendering, persistence, deletion) changes.
 */
final readonly class DecisionProducerRegistry
{
    public const string TAG = 'video-edits.decision-producers';

    /**
     * @param  iterable<CutDecisionProducer>  $producers
     */
    public function __construct(
        private iterable $producers,
    ) {}

    /**
     * @return list<CutDecisionProducer>
     */
    #[\NoDiscard]
    public function forContext(DecisionContext $context): array
    {
        $selected = [];

        foreach ($this->producers as $producer) {
            if ($producer->supports($context)) {
                $selected[] = $producer;
            }
        }

        return $selected;
    }
}
