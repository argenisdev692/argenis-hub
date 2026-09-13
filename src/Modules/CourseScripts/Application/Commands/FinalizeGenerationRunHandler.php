<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;

/**
 * Closes a run once its batch has finished (FR-18). A run already closed —
 * cancelled or stopped at the ceiling — keeps its status.
 */
final readonly class FinalizeGenerationRunHandler
{
    public function __construct(
        private GenerationRunRepositoryPort $runs,
        private CourseRepositoryPort $courses,
    ) {}

    public function handle(int $runId): void
    {
        $run = $this->runs->findById($runId);

        if ($run === null) {
            return;
        }

        if ($run->status->isActive()) {
            $this->runs->skipPendingOutcomes($runId);
            $run = $run->fresh() ?? $run;

            $status = match (true) {
                $run->videos_failed === 0 => GenerationRunStatus::Completed,
                $run->videos_completed > 0 => GenerationRunStatus::PartiallyFailed,
                default => GenerationRunStatus::Failed,
            };

            $this->runs->finish($runId, $status);
        }

        $this->courses->refreshStatus($run->course_id);
    }
}
