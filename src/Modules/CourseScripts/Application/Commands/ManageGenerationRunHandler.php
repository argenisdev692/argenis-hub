<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Domain\Enums\GenerationRunKind;
use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Enums\GenerationScope;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\RunInProgressException;
use Modules\CourseScripts\Domain\Exceptions\RunRequestRejectedException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\GenerationDispatcherPort;
use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Cancel a run (FR-21) or retry only its failed videos (FR-20).
 */
final readonly class ManageGenerationRunHandler
{
    public function __construct(
        private GenerationRunRepositoryPort $runs,
        private CourseRepositoryPort $courses,
        private GenerationDispatcherPort $dispatcher,
        private StartGenerationRunHandler $start,
        private AuditPort $audit,
    ) {}

    /**
     * Completed videos are kept; nothing further starts.
     *
     * @throws CourseNotFoundException
     */
    public function cancel(string $runUuid, int $userId, ?object $causer = null): CourseGenerationRunEloquentModel
    {
        $run = $this->runs->findOwned($runUuid, $userId) ?? throw new CourseNotFoundException;

        if (! $run->status->isActive()) {
            return $run;
        }

        if ($run->batch_id !== null) {
            $this->dispatcher->cancel($run->batch_id);
        }

        $this->runs->skipPendingOutcomes($run->id);
        $run = $this->runs->finish($run->id, GenerationRunStatus::Cancelled, 'cancelled_by_author');
        $this->courses->refreshStatus($run->course_id);

        $this->audit->log('course_scripts.run_cancelled', $run, ['run_uuid' => $run->uuid], $causer, 'course_scripts.run');

        return $run;
    }

    /**
     * A new run over the failed videos, with the original writer and review choice.
     *
     * @throws CourseNotFoundException
     * @throws RunRequestRejectedException
     * @throws RunInProgressException
     */
    public function retryFailed(string $runUuid, int $userId, ?object $causer = null): CourseGenerationRunEloquentModel
    {
        $run = $this->runs->findOwned($runUuid, $userId) ?? throw new CourseNotFoundException;
        $failed = $this->runs->failedVideoIds($run->id);

        if ($run->status->isActive() || $failed === []) {
            throw RunRequestRejectedException::notRetryable();
        }

        $course = $this->courses->findById($run->course_id) ?? throw new CourseNotFoundException;

        return $this->start->start(
            course: $course,
            videoIds: $failed,
            writerProvider: $run->writer_provider,
            withReview: $run->with_review,
            scope: GenerationScope::Selection->value,
            blockId: null,
            userId: $userId,
            estimate: ['ai_write_calls' => 0, 'ai_review_calls' => 0, 'research_calls' => 0],
            kind: GenerationRunKind::Generation,
            causer: $causer,
        );
    }
}
