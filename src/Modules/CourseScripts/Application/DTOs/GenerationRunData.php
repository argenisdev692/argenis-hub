<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\DTOs;

use Modules\CourseScripts\Domain\Enums\GenerationRunKind;
use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Enums\GenerationScope;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoOutcomeEloquentModel;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A run and its progress (US-9, US-12 · FR-22, FR-24). The second-review choice
 * and the independence warning are part of the contract (FR-41).
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class GenerationRunData extends Data
{
    /**
     * @param  array{number: int, title: string}|null  $currentVideo
     * @param  list<array{video_uuid: string, number: int, title: string, status: string, failure_reason: ?string, review_iterations: int}>  $outcomes
     */
    public function __construct(
        public string $uuid,
        public string $courseUuid,
        public GenerationRunKind $kind,
        public GenerationScope $scope,
        public GenerationRunStatus $status,
        public string $writerProvider,
        public bool $withReview,
        public ?string $reviewerProvider,
        public bool $reviewerNotIndependent,
        public int $videosTotal,
        public int $videosCompleted,
        public int $videosFailed,
        public ?array $currentVideo,
        public int $progress,
        public int $estimatedAiWriteCalls,
        public int $estimatedAiReviewCalls,
        public int $estimatedResearchCalls,
        public int $aiWriteCallsConsumed,
        public int $aiReviewCallsConsumed,
        public int $researchCallsConsumed,
        public int $aiCallCeiling,
        public int $researchCallCeiling,
        public ?string $stopReason,
        public array $outcomes,
        public ?string $startedAt,
        public ?string $finishedAt,
    ) {}

    public static function fromModel(CourseGenerationRunEloquentModel $run, ?int $liveProgress = null): self
    {
        $run->loadMissing([
            'course:id,uuid',
            'currentVideo:id,number,title',
            'outcomes' => static fn ($query) => $query->orderBy('position'),
            'outcomes.video:id,uuid,number,title',
        ]);

        $done = $run->videos_completed + $run->videos_failed;

        return new self(
            uuid: $run->uuid,
            courseUuid: $run->course->uuid,
            kind: $run->kind,
            scope: $run->scope,
            status: $run->status,
            writerProvider: $run->writer_provider,
            withReview: $run->with_review,
            reviewerProvider: $run->reviewer_provider,
            reviewerNotIndependent: $run->reviewer_not_independent,
            videosTotal: $run->videos_total,
            videosCompleted: $run->videos_completed,
            videosFailed: $run->videos_failed,
            currentVideo: $run->currentVideo === null ? null : ['number' => $run->currentVideo->number, 'title' => $run->currentVideo->title],
            progress: $run->status->isActive() && $liveProgress !== null
                ? $liveProgress
                : ($run->videos_total === 0 ? 0 : (int) round($done * 100 / $run->videos_total)),
            estimatedAiWriteCalls: $run->estimated_ai_write_calls,
            estimatedAiReviewCalls: $run->estimated_ai_review_calls,
            estimatedResearchCalls: $run->estimated_research_calls,
            aiWriteCallsConsumed: $run->ai_write_calls_consumed,
            aiReviewCallsConsumed: $run->ai_review_calls_consumed,
            researchCallsConsumed: $run->research_calls_consumed,
            aiCallCeiling: $run->ai_call_ceiling,
            researchCallCeiling: $run->research_call_ceiling,
            stopReason: $run->stop_reason,
            outcomes: $run->outcomes->map(static fn (CourseVideoOutcomeEloquentModel $outcome): array => [
                'video_uuid' => $outcome->video->uuid,
                'number' => $outcome->video->number,
                'title' => $outcome->video->title,
                'status' => $outcome->status->value,
                'failure_reason' => $outcome->failure_reason,
                'review_iterations' => $outcome->review_iterations,
            ])->values()->all(),
            startedAt: $run->started_at?->toIso8601String(),
            finishedAt: $run->finished_at?->toIso8601String(),
        );
    }
}
