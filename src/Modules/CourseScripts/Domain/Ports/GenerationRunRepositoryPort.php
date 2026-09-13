<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Domain\Ports;

use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Exceptions\RunInProgressException;
use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoOutcomeEloquentModel;

/**
 * Persistence of generation runs and their per-video outcomes (US-9 · FR-18,
 * FR-24). Counters are incremented atomically in the database so concurrent
 * workers can never lose a call.
 */
interface GenerationRunRepositoryPort
{
    /**
     * Creates the run and one pending outcome per video, in course order.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $videoIdsInOrder
     *
     * @throws RunInProgressException when the course already has an active run
     */
    public function create(array $attributes, array $videoIdsInOrder): CourseGenerationRunEloquentModel;

    public function findOwned(string $uuid, int $userId): ?CourseGenerationRunEloquentModel;

    public function findById(int $id): ?CourseGenerationRunEloquentModel;

    public function activeForCourse(int $courseId): ?CourseGenerationRunEloquentModel;

    public function setBatchId(CourseGenerationRunEloquentModel $run, string $batchId): void;

    public function markRunning(int $runId): void;

    public function addUsage(int $runId, CallUsage $usage): CourseGenerationRunEloquentModel;

    public function startOutcome(int $runId, int $videoId): void;

    public function completeOutcome(int $runId, int $videoId, CallUsage $usage, int $reviewIterations): void;

    public function failOutcome(int $runId, int $videoId, string $reasonCode, CallUsage $usage): void;

    public function outcome(int $runId, int $videoId): ?CourseVideoOutcomeEloquentModel;

    /**
     * Marks every still-pending outcome skipped (cancel, ceiling).
     */
    public function skipPendingOutcomes(int $runId): int;

    public function finish(int $runId, GenerationRunStatus $status, ?string $stopReason = null): CourseGenerationRunEloquentModel;

    /**
     * @return list<int> video ids, in course order
     */
    public function failedVideoIds(int $runId): array;
}
