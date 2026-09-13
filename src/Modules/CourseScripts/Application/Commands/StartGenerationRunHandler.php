<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\CourseScripts\Application\DTOs\StartGenerationRunData;
use Modules\CourseScripts\Application\Generation\RunScopeResolver;
use Modules\CourseScripts\Application\Queries\EstimateRunCallsHandler;
use Modules\CourseScripts\Domain\Enums\CourseStatus;
use Modules\CourseScripts\Domain\Enums\GenerationRunKind;
use Modules\CourseScripts\Domain\Enums\GenerationRunStatus;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Exceptions\RunInProgressException;
use Modules\CourseScripts\Domain\Exceptions\RunRequestRejectedException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\GenerationDispatcherPort;
use Modules\CourseScripts\Domain\Ports\GenerationRunRepositoryPort;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseGenerationRunEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;
use Shared\Domain\Ports\AuditPort;

/**
 * Starts a generation run (US-9, US-12, US-13 · plan §3.4).
 *
 * Guards, in order: one active run per course (D8), a non-empty scope, the
 * estimate the author confirmed still matches, and it fits the ceilings.
 * Then the run and its outcomes are stored and one batch is dispatched.
 */
final readonly class StartGenerationRunHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private GenerationRunRepositoryPort $runs,
        private RunScopeResolver $scopes,
        private EstimateRunCallsHandler $estimates,
        private GenerationDispatcherPort $dispatcher,
        private AuditPort $audit,
        private Config $config,
    ) {}

    /**
     * @throws CourseNotFoundException
     * @throws RunInProgressException
     * @throws RunRequestRejectedException
     */
    public function handle(string $courseUuid, StartGenerationRunData $data, int $userId, ?object $causer = null, GenerationRunKind $kind = GenerationRunKind::Generation): CourseGenerationRunEloquentModel
    {
        $course = $this->courses->findOwned($courseUuid, $userId) ?? throw new CourseNotFoundException;

        if ($this->runs->activeForCourse($course->id) !== null) {
            throw new RunInProgressException;
        }

        ['videos' => $videos, 'block_id' => $blockId] = $this->scopes->resolve($course, $data);
        $estimate = $this->estimates->forCourse($course, $data);

        if ($estimate->confirmation() !== array_map(intval(...), $data->confirmedEstimate)) {
            throw RunRequestRejectedException::estimateMismatch($estimate->confirmation());
        }

        if (! $estimate->fits()) {
            throw RunRequestRejectedException::exceedsCeiling($estimate->confirmation());
        }

        return $this->start($course, array_map(static fn (CourseVideoEloquentModel $video): int => $video->id, $videos), $data->writerProvider, $data->reviewRequested(), $data->scope->value, $blockId, $userId, $estimate->confirmation(), $kind, $causer);
    }

    /**
     * Shared by retries, regenerations and forced practice packs, which skip
     * the author-confirmed estimate of a full run.
     *
     * @param  list<int>  $videoIds
     * @param  array{ai_write_calls: int, ai_review_calls: int, research_calls: int}  $estimate
     */
    public function start(
        CourseEloquentModel $course,
        array $videoIds,
        string $writerProvider,
        bool $withReview,
        string $scope,
        ?int $blockId,
        int $userId,
        array $estimate,
        GenerationRunKind $kind,
        ?object $causer = null,
        ?string $feedbackNote = null,
    ): CourseGenerationRunEloquentModel {
        if ($this->runs->activeForCourse($course->id) !== null) {
            throw new RunInProgressException;
        }

        $reviewer = $withReview ? (string) $this->config->get('ai.default_for_evaluation', $writerProvider) : null;

        $run = $this->runs->create([
            'course_id' => $course->id,
            'user_id' => $userId,
            'kind' => $kind,
            'scope' => $scope,
            'scoped_block_id' => $blockId,
            'writer_provider' => $writerProvider,
            'reviewer_provider' => $reviewer,
            'with_review' => $withReview,
            'reviewer_not_independent' => $withReview && $reviewer === $writerProvider,
            'status' => GenerationRunStatus::Queued,
            'estimated_ai_write_calls' => $estimate['ai_write_calls'],
            'estimated_ai_review_calls' => $estimate['ai_review_calls'],
            'estimated_research_calls' => $estimate['research_calls'],
            'ai_call_ceiling' => (int) $this->config->get('course-scripts.runs.max_ai_calls_per_run', 900),
            'research_call_ceiling' => (int) $this->config->get('course-scripts.runs.max_research_calls_per_run', 250),
        ], $videoIds);

        $this->courses->updateStatus($course, CourseStatus::Generating);

        $this->audit->log('course_scripts.run_started', $run, [
            'run_uuid' => $run->uuid,
            'course_uuid' => $course->uuid,
            'kind' => $kind->value,
            'scope' => $scope,
            'video_count' => count($videoIds),
            'writer_provider' => $writerProvider,
            'with_review' => $withReview,
        ], $causer, 'course_scripts.run');

        $needsPreparation = $course->prepared_at === null || $course->bible === null;
        $batchId = $this->dispatcher->dispatch($run->id, $course->id, $videoIds, $needsPreparation, $writerProvider, $feedbackNote);

        // With the sync driver the whole batch has already run inside dispatch():
        // re-read instead of trusting the in-memory row.
        $run = $this->runs->findById($run->id) ?? $run;
        $this->runs->setBatchId($run, $batchId);

        return $run;
    }
}
