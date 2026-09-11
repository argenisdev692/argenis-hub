<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * What the edit removed (US-5).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class VideoEditSummaryData extends Data
{
    public function __construct(
        public ?int $originalDurationMs,
        public ?int $finalDurationMs,
        public ?int $removedDurationMs,
        public int $appliedCutCount,
        public int $rejectedDecisionCount,
    ) {}

    public static function fromModel(VideoEditEloquentModel $edit): self
    {
        return new self(
            originalDurationMs: $edit->original_duration_ms,
            finalDurationMs: $edit->final_duration_ms,
            removedDurationMs: $edit->removed_duration_ms,
            appliedCutCount: $edit->applied_cut_count,
            rejectedDecisionCount: $edit->rejected_decision_count,
        );
    }
}
