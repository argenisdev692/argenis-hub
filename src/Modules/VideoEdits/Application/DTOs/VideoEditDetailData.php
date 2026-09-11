<?php

declare(strict_types=1);

namespace Modules\VideoEdits\Application\DTOs;

use Carbon\CarbonInterface;
use Modules\VideoEdits\Domain\Enums\ProcessingStage;
use Modules\VideoEdits\Domain\Enums\VideoEditMode;
use Modules\VideoEdits\Domain\Enums\VideoEditStatus;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditAppliedCutEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditCutDecisionEloquentModel;
use Modules\VideoEdits\Infrastructure\Persistence\Eloquent\Models\VideoEditEloquentModel;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Full view of one edit (E4 · US-4/5/7): live status, the re-edit prefill
 * (`parameters`), the removal summary, every decision, and what the owner may
 * do next. Object paths and signed URLs are never part of it (OWASP §12).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class VideoEditDetailData extends Data
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<VideoEditSourceData>  $sources
     * @param  list<AppliedCutData>  $appliedCuts
     * @param  list<CutDecisionData>  $decisions
     * @param  list<string>  $warnings
     */
    public function __construct(
        public string $uuid,
        public VideoEditMode $mode,
        public VideoEditStatus $status,
        public int $progressPercent,
        public ?ProcessingStage $currentStage,
        public int $attempts,
        public array $parameters,
        public ?string $previousEditUuid,
        #[DataCollectionOf(VideoEditSourceData::class)]
        public array $sources,
        public VideoEditSummaryData $summary,
        #[DataCollectionOf(AppliedCutData::class)]
        public array $appliedCuts,
        #[DataCollectionOf(CutDecisionData::class)]
        public array $decisions,
        public array $warnings,
        public ?VideoEditFailureData $failure,
        public ?string $retryAvailableUntil,
        public bool $canRetry,
        public bool $canDelete,
        public bool $canDownload,
        public ?string $createdAt,
        public ?string $queuedAt,
        public ?string $startedAt,
        public ?string $completedAt,
        public ?string $failedAt,
    ) {}

    /**
     * Expects `sources`, `appliedCuts`, `cutDecisions` and `previousEdit:id,uuid` eager-loaded.
     */
    public static function fromModel(VideoEditEloquentModel $edit, CarbonInterface $now): self
    {
        $retryable = $edit->status === VideoEditStatus::Failed
            && $edit->sources_purged_at === null
            && $edit->sources_expire_at?->isAfter($now) === true;

        return new self(
            uuid: $edit->uuid,
            mode: $edit->mode,
            status: $edit->status,
            progressPercent: $edit->progress_percent,
            currentStage: $edit->current_stage,
            attempts: $edit->attempts,
            parameters: $edit->parameters,
            previousEditUuid: $edit->previousEdit?->uuid,
            sources: array_values($edit->sources->map(VideoEditSourceData::fromModel(...))->all()),
            summary: VideoEditSummaryData::fromModel($edit),
            appliedCuts: array_values($edit->appliedCuts->map(
                static fn (VideoEditAppliedCutEloquentModel $cut): AppliedCutData => AppliedCutData::fromModel($cut),
            )->all()),
            decisions: array_values($edit->cutDecisions->map(
                static fn (VideoEditCutDecisionEloquentModel $decision): CutDecisionData => CutDecisionData::fromModel($decision),
            )->all()),
            warnings: array_values($edit->warnings ?? []),
            failure: $edit->failure_code === null ? null : new VideoEditFailureData(
                code: $edit->failure_code,
                message: (string) $edit->failure_message,
                details: $edit->failure_details,
            ),
            retryAvailableUntil: $retryable ? $edit->sources_expire_at?->toIso8601String() : null,
            canRetry: $retryable,
            canDelete: $edit->status->isDeletable(),
            canDownload: $edit->status === VideoEditStatus::Completed && $edit->result_path !== null,
            createdAt: $edit->created_at?->toIso8601String(),
            queuedAt: $edit->queued_at?->toIso8601String(),
            startedAt: $edit->started_at?->toIso8601String(),
            completedAt: $edit->completed_at?->toIso8601String(),
            failedAt: $edit->failed_at?->toIso8601String(),
        );
    }
}
