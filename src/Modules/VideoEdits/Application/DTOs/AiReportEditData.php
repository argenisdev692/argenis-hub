<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The header of the AI decision report (US-14): which edit, and what it removed.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AiReportEditData extends Data
{
    public function __construct(
        public string $uuid,
        public VideoEditMode $mode,
        public VideoEditStatus $status,
        public ?int $originalDurationMs,
        public ?int $finalDurationMs,
        public ?int $removedDurationMs,
        public int $appliedCutCount,
        public int $rejectedDecisionCount,
        public ?string $scriptName,
        public ?string $completedAt,
    ) {}

    public static function fromModel(VideoEditEloquentModel $edit): self
    {
        return new self(
            uuid: $edit->uuid,
            mode: $edit->mode,
            status: $edit->status,
            originalDurationMs: $edit->original_duration_ms,
            finalDurationMs: $edit->final_duration_ms,
            removedDurationMs: $edit->removed_duration_ms,
            appliedCutCount: $edit->applied_cut_count,
            rejectedDecisionCount: $edit->rejected_decision_count,
            scriptName: $edit->script?->original_name,
            completedAt: $edit->completed_at?->toIso8601String(),
        );
    }
}
