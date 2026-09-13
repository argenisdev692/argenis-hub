<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Infrastructure\Persistence\Repositories;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Enums\VideoOutcomeStatus;
use Modules\CourseScripts\Domain\Exceptions\RunInProgressException;
use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;
use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoOutcomeEloquentModel;

final readonly class EloquentGenerationRunRepository implements GenerationRunRepositoryPort
{
    public function create(array $attributes, array $videoIdsInOrder): CourseGenerationRunEloquentModel
    {
        try {
            return DB::transaction(static function () use ($attributes, $videoIdsInOrder): CourseGenerationRunEloquentModel {
                $run = CourseGenerationRunEloquentModel::query()->create([
                    ...$attributes,
                    'videos_total' => count($videoIdsInOrder),
                ]);

                foreach (array_values($videoIdsInOrder) as $position => $videoId) {
                    CourseVideoOutcomeEloquentModel::query()->create([
                        'course_generation_run_id' => $run->id,
                        'course_video_id' => $videoId,
                        'position' => $position,
                        'status' => VideoOutcomeStatus::Pending,
                    ]);
                }

                return $run;
            });
        } catch (QueryException $exception) {
            // The partial unique index is the authority on "one active run".
            if (str_contains(strtolower($exception->getMessage()), 'one_active_per_course') || str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw new RunInProgressException;
            }

            throw $exception;
        }
    }

    public function findOwned(string $uuid, int $userId): ?CourseGenerationRunEloquentModel
    {
        return CourseGenerationRunEloquentModel::query()
            ->where('uuid', $uuid)
            ->where('user_id', $userId)
            ->with([
                'course:id,uuid,title',
                'currentVideo:id,uuid,number,title',
                'outcomes' => static fn ($query) => $query->orderBy('position'),
                'outcomes.video:id,uuid,number,title',
            ])
            ->first();
    }

    public function findById(int $id): ?CourseGenerationRunEloquentModel
    {
        return CourseGenerationRunEloquentModel::query()->find($id);
    }

    public function activeForCourse(int $courseId): ?CourseGenerationRunEloquentModel
    {
        return CourseGenerationRunEloquentModel::query()
            ->where('course_id', $courseId)
            ->whereIn('status', GenerationRunStatus::activeValues())
            ->first();
    }

    public function setBatchId(CourseGenerationRunEloquentModel $run, string $batchId): void
    {
        CourseGenerationRunEloquentModel::query()->whereKey($run->id)->update(['batch_id' => $batchId]);
    }

    public function markRunning(int $runId): void
    {
        CourseGenerationRunEloquentModel::query()
            ->whereKey($runId)
            ->where('status', GenerationRunStatus::Queued->value)
            ->update(['status' => GenerationRunStatus::Running->value, 'started_at' => now()]);
    }

    public function addUsage(int $runId, CallUsage $usage): CourseGenerationRunEloquentModel
    {
        CourseGenerationRunEloquentModel::query()->whereKey($runId)->update([
            'ai_write_calls_consumed' => DB::raw('ai_write_calls_consumed + '.(int) $usage->aiWrite),
            'ai_review_calls_consumed' => DB::raw('ai_review_calls_consumed + '.(int) $usage->aiReview),
            'research_calls_consumed' => DB::raw('research_calls_consumed + '.(int) $usage->research),
        ]);

        return CourseGenerationRunEloquentModel::query()->findOrFail($runId);
    }

    public function startOutcome(int $runId, int $videoId): void
    {
        DB::transaction(static function () use ($runId, $videoId): void {
            CourseVideoOutcomeEloquentModel::query()
                ->where('course_generation_run_id', $runId)
                ->where('course_video_id', $videoId)
                ->update([
                    'status' => VideoOutcomeStatus::Running->value,
                    'started_at' => now(),
                    'finished_at' => null,
                    'failure_reason' => null,
                    'attempts' => DB::raw('attempts + 1'),
                ]);

            CourseGenerationRunEloquentModel::query()->whereKey($runId)->update(['current_video_id' => $videoId]);
        });
    }

    public function completeOutcome(int $runId, int $videoId, CallUsage $usage, int $reviewIterations): void
    {
        DB::transaction(function () use ($runId, $videoId, $usage, $reviewIterations): void {
            $this->closeOutcome($runId, $videoId, VideoOutcomeStatus::Completed, null, $usage, $reviewIterations);

            CourseGenerationRunEloquentModel::query()->whereKey($runId)->update([
                'videos_completed' => DB::raw('videos_completed + 1'),
                'current_video_id' => null,
            ]);
        });
    }

    public function failOutcome(int $runId, int $videoId, string $reasonCode, CallUsage $usage): void
    {
        DB::transaction(function () use ($runId, $videoId, $reasonCode, $usage): void {
            $this->closeOutcome($runId, $videoId, VideoOutcomeStatus::Failed, $reasonCode, $usage, 0);

            CourseGenerationRunEloquentModel::query()->whereKey($runId)->update([
                'videos_failed' => DB::raw('videos_failed + 1'),
                'current_video_id' => null,
            ]);
        });
    }

    public function outcome(int $runId, int $videoId): ?CourseVideoOutcomeEloquentModel
    {
        return CourseVideoOutcomeEloquentModel::query()
            ->where('course_generation_run_id', $runId)
            ->where('course_video_id', $videoId)
            ->first();
    }

    public function skipPendingOutcomes(int $runId): int
    {
        return CourseVideoOutcomeEloquentModel::query()
            ->where('course_generation_run_id', $runId)
            ->whereIn('status', [VideoOutcomeStatus::Pending->value, VideoOutcomeStatus::Running->value])
            ->update(['status' => VideoOutcomeStatus::Skipped->value, 'finished_at' => now()]);
    }

    public function finish(int $runId, GenerationRunStatus $status, ?string $stopReason = null): CourseGenerationRunEloquentModel
    {
        CourseGenerationRunEloquentModel::query()
            ->whereKey($runId)
            ->whereIn('status', GenerationRunStatus::activeValues())
            ->update([
                'status' => $status->value,
                'stop_reason' => $stopReason,
                'current_video_id' => null,
                'finished_at' => now(),
            ]);

        return CourseGenerationRunEloquentModel::query()->findOrFail($runId);
    }

    public function failedVideoIds(int $runId): array
    {
        return CourseVideoOutcomeEloquentModel::query()
            ->where('course_generation_run_id', $runId)
            ->where('status', VideoOutcomeStatus::Failed->value)
            ->orderBy('position')
            ->pluck('course_video_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    private function closeOutcome(int $runId, int $videoId, VideoOutcomeStatus $status, ?string $reason, CallUsage $usage, int $reviewIterations): void
    {
        CourseVideoOutcomeEloquentModel::query()
            ->where('course_generation_run_id', $runId)
            ->where('course_video_id', $videoId)
            ->update([
                'status' => $status->value,
                'failure_reason' => $reason === null ? null : mb_substr($reason, 0, 500),
                'review_iterations' => $reviewIterations,
                'ai_write_calls' => DB::raw('ai_write_calls + '.(int) $usage->aiWrite),
                'ai_review_calls' => DB::raw('ai_review_calls + '.(int) $usage->aiReview),
                'research_calls' => DB::raw('research_calls + '.(int) $usage->research),
                'finished_at' => now(),
            ]);
    }
}
