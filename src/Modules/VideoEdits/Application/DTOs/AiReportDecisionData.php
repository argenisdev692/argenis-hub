<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Domain\Enums\CutReason;
use Modules\VideoEdits\Domain\Enums\DecisionOutcome;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditCutDecisionEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * One AI decision as the report prints it (US-14): applied or rejected, with
 * the evidence the model quoted.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AiReportDecisionData extends Data
{
    public function __construct(
        public CutReason $reason,
        public int $startMs,
        public int $endMs,
        public int $durationMs,
        public ?float $confidence,
        public DecisionOutcome $outcome,
        public ?string $rejectionReason,
        public ?string $evidence,
    ) {}

    /**
     * `rejection_reason` is stored as a plain string (no enum cast) and
     * `confidence` as a `decimal:3` string, so both are normalised here rather
     * than trusted to be enums / floats.
     */
    public static function fromModel(VideoEditCutDecisionEloquentModel $decision): self
    {
        $evidence = is_array($decision->evidence) ? ($decision->evidence['text'] ?? null) : null;

        return new self(
            reason: $decision->reason,
            startMs: $decision->start_ms,
            endMs: $decision->end_ms,
            durationMs: $decision->end_ms - $decision->start_ms,
            confidence: $decision->confidence === null ? null : (float) $decision->confidence,
            outcome: $decision->outcome,
            rejectionReason: $decision->rejection_reason,
            evidence: is_string($evidence) ? $evidence : null,
        );
    }
}
