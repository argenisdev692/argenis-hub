<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOrigin;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditCutDecisionEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Every decision a producer proposed, applied or rejected (EX-8).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class CutDecisionData extends Data
{
    public function __construct(
        public string $producer,
        public CutReason $reason,
        public DecisionOrigin $origin,
        public int $startMs,
        public int $endMs,
        public ?float $confidence,
        public DecisionOutcome $outcome,
        public ?string $rejectionReason,
    ) {}

    public static function fromModel(VideoEditCutDecisionEloquentModel $decision): self
    {
        return new self(
            producer: $decision->producer,
            reason: $decision->reason,
            origin: $decision->origin,
            startMs: $decision->start_ms,
            endMs: $decision->end_ms,
            confidence: $decision->confidence === null ? null : (float) $decision->confidence,
            outcome: $decision->outcome,
            rejectionReason: $decision->rejection_reason,
        );
    }
}
