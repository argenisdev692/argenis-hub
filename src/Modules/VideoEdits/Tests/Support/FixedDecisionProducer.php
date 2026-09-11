<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Tests\Support;

use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Ports\CutDecisionProducer;
use Modules\VideoEdits\Domain\ValueObjects\CutDecision;
use Modules\VideoEdits\Domain\ValueObjects\DecisionContext;

/**
 * Stands in for a future producer (V2 speech detector, V3 AI analyzer): it
 * emits a fixed set of decisions with confidence and evidence, and is plugged
 * in only by tagging it — the roadmap-readiness proof (spec §11).
 */
final readonly class FixedDecisionProducer implements CutDecisionProducer
{
    public const string NAME = 'roadmap_fixture';

    /**
     * @param  list<CutDecision>  $decisions
     */
    public function __construct(
        private array $decisions = [],
    ) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function supports(DecisionContext $context): bool
    {
        return $context->mode === VideoEditMode::AutoEdit;
    }

    public function produce(DecisionContext $context): array
    {
        return $this->decisions;
    }
}
