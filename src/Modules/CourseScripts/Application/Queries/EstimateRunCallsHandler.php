<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Queries;

use Illuminate\Contracts\Config\Repository as Config;
use Modules\CourseScripts\Application\DTOs\EstimateRunData;
use Modules\CourseScripts\Application\Generation\RunScopeResolver;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\ResearchFindingRepositoryPort;
use Modules\CourseScripts\Domain\Services\CallEstimator;
use Modules\CourseScripts\Domain\ValueObjects\CallEstimate;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseVideoEloquentModel;

/**
 * The call estimate the author confirms before a run (US-12).
 */
final readonly class EstimateRunCallsHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private RunScopeResolver $scopes,
        private CallEstimator $estimator,
        private ResearchFindingRepositoryPort $findings,
        private Config $config,
    ) {}

    /**
     * @throws CourseNotFoundException
     */
    public function handle(string $courseUuid, EstimateRunData $data, int $userId): CallEstimate
    {
        $course = $this->courses->findOwned($courseUuid, $userId) ?? throw new CourseNotFoundException;

        return $this->forCourse($course, $data);
    }

    public function forCourse(CourseEloquentModel $course, EstimateRunData $data): CallEstimate
    {
        ['videos' => $videos] = $this->scopes->resolve($course, $data);

        return $this->estimator->estimate(
            videoMinutes: array_map(static fn (CourseVideoEloquentModel $video): int => $video->declared_duration_minutes ?? $course->default_video_minutes, $videos),
            withReview: $data->reviewRequested(),
            needsBible: $course->bible === null,
            needsSubjectResearch: ! $this->findings->hasCourseFindings($course->id),
            aiCeiling: (int) $this->config->get('course-scripts.runs.max_ai_calls_per_run', 900),
            researchCeiling: (int) $this->config->get('course-scripts.runs.max_research_calls_per_run', 250),
        );
    }
}
