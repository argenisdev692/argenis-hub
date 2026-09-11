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
 * One row of the edit history (E1 · US-6).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class VideoEditListItemData extends Data
{
    public function __construct(
        public string $uuid,
        public VideoEditMode $mode,
        public VideoEditStatus $status,
        public int $progressPercent,
        public int $sourceCount,
        public ?int $finalDurationMs,
        public int $appliedCutCount,
        public ?string $createdAt,
        public ?string $completedAt,
    ) {}

    /**
     * Expects `sources_count` from `withCount('sources')`.
     */
    public static function fromModel(VideoEditEloquentModel $edit): self
    {
        return new self(
            uuid: $edit->uuid,
            mode: $edit->mode,
            status: $edit->status,
            progressPercent: $edit->progress_percent,
            sourceCount: (int) $edit->sources_count,
            finalDurationMs: $edit->final_duration_ms,
            appliedCutCount: $edit->applied_cut_count,
            createdAt: $edit->created_at?->toIso8601String(),
            completedAt: $edit->completed_at?->toIso8601String(),
        );
    }
}
