<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Application\DTOs\PrepareCourseData;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Domain\Ports\GenerationDispatcherPort;
use Shared\Domain\Ports\AuditPort;

/**
 * The author's standalone "prepare" action (US-4): queues the bible proposal
 * and subject research through the dispatcher port, so the controller never
 * touches a queue job and the call is audited like every other AI spend.
 */
final readonly class RequestCoursePreparationHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private GenerationDispatcherPort $dispatcher,
        private AuditPort $audit,
    ) {}

    /**
     * @throws CourseNotFoundException
     */
    public function handle(string $courseUuid, PrepareCourseData $data, int $userId, ?object $causer = null): void
    {
        $course = $this->courses->findOwned($courseUuid, $userId) ?? throw new CourseNotFoundException;

        $this->dispatcher->prepare($course->id, $data->writerProvider);

        $this->audit->log(
            'course_scripts.preparation_requested',
            $course,
            ['course_uuid' => $course->uuid, 'writer_provider' => $data->writerProvider],
            $causer,
            'course_scripts.course',
        );
    }
}
