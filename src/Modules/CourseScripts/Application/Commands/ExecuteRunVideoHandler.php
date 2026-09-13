<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\CourseScripts\Application\Generation\GenerateVideoScriptCommand;
use Modules\CourseScripts\Domain\Enums\GenerationRunKind;
use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Enums\VideoScriptStatus;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\GenerationProviderException;
use Modules\CourseScripts\Domain\Exceptions\ScriptValidationException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ScriptVersionRepositoryPort;
use Modules\CourseScripts\Domain\ValueObjects\CallUsage;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * One video inside a run (plan §3.5, R2.2). Never throws: a failure is written
 * to the video's outcome and the chain continues (FR-18, FR-19).
 *
 * Returns false when the run must not continue — cancelled, or a ceiling was
 * reached (FR-23) — so the job can cancel the rest of the batch.
 */
final readonly class ExecuteRunVideoHandler
{
    public function __construct(
        private GenerationRunRepositoryPort $runs,
        private CourseRepositoryPort $courses,
        private ScriptVersionRepositoryPort $versions,
        private GenerateVideoScriptHandler $generate,
        private BuildDeliverablesHandler $deliverables,
        private Config $config,
        private LoggerInterface $logger,
    ) {}

    public function handle(int $runId, int $videoId, ?string $feedbackNote = null): bool
    {
        $run = $this->runs->findById($runId);

        if ($run === null || ! $run->status->isActive()) {
            return false;
        }

        if ($this->ceilingReached($run)) {
            $this->stopAtCeiling($run);

            return false;
        }

        $this->runs->markRunning($runId);
        $this->runs->startOutcome($runId, $videoId);
        $this->courses->updateVideoStatus($videoId, VideoScriptStatus::Generating);

        $usage = CallUsage::none();

        try {
            $result = $this->generate->handle(new GenerateVideoScriptCommand(
                courseId: $run->course_id,
                videoId: $videoId,
                writerProvider: $run->writer_provider,
                withReview: $run->with_review,
                reviewerProvider: $run->reviewer_provider,
                runId: $run->id,
                forcePractice: $run->kind === GenerationRunKind::ForcePractice,
                feedbackNote: $feedbackNote,
                accept: $run->kind !== GenerationRunKind::Regeneration || (bool) $this->config->get('course-scripts.versions.auto_accept_regenerations', false),
            ), $usage);

            $version = $this->versions->findById($result->scriptVersionId);

            if ($version !== null && $version->is_accepted) {
                $this->deliverables->handle($version);
            }

            $this->runs->completeOutcome($runId, $videoId, $result->usage, $result->reviewIterations);
            $run = $this->runs->addUsage($runId, $result->usage);
            $this->courses->updateVideoStatus($videoId, VideoScriptStatus::Generated);
        } catch (Throwable $exception) {
            $this->logger->warning('course_scripts.video_failed', [
                'run_id' => $runId,
                'video_id' => $videoId,
                'exception' => $exception::class,
            ]);

            $usage ??= CallUsage::none();
            $this->runs->failOutcome($runId, $videoId, $this->reasonCode($exception), $usage);
            $run = $this->runs->addUsage($runId, $usage);
            $this->courses->updateVideoStatus(
                $videoId,
                $this->versions->findAccepted($videoId) !== null ? VideoScriptStatus::Generated : VideoScriptStatus::Failed,
            );
        }

        if ($this->ceilingReached($run) && $this->hasPendingVideos($run)) {
            $this->stopAtCeiling($run);

            return false;
        }

        return true;
    }

    private function ceilingReached(CourseGenerationRunEloquentModel $run): bool
    {
        return $run->aiCallsConsumed() >= $run->ai_call_ceiling
            || $run->research_calls_consumed >= $run->research_call_ceiling;
    }

    private function hasPendingVideos(CourseGenerationRunEloquentModel $run): bool
    {
        return $run->videos_completed + $run->videos_failed < $run->videos_total;
    }

    private function stopAtCeiling(CourseGenerationRunEloquentModel $run): void
    {
        $this->runs->skipPendingOutcomes($run->id);
        $this->runs->finish($run->id, GenerationRunStatus::StoppedAtCeiling, 'call_ceiling_reached');
        $this->courses->refreshStatus($run->course_id);
    }

    /**
     * A sanitized code — never provider text, paths or prompts (FR-55).
     */
    private function reasonCode(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof GenerationProviderException => $exception->reasonCode.':'.$exception->step,
            $exception instanceof ScriptValidationException => ScriptValidationException::CODE.':'.$exception->step,
            $exception instanceof CourseNotFoundException => 'video_not_found',
            default => 'unexpected_error',
        };
    }
}
