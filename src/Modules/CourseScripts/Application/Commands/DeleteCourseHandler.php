<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Shared\Domain\Ports\AuditPort;

/**
 * Soft-deletes a course (US-11). Stored files are purged when the course is
 * pruned (see `CourseEloquentModel::prunable()`), so an accidental delete is
 * recoverable for the configured window.
 */
final readonly class DeleteCourseHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private AuditPort $audit,
    ) {}

    /**
     * @throws CourseNotFoundException
     */
    public function handle(string $uuid, int $userId, ?object $causer = null): void
    {
        $course = $this->courses->findOwned($uuid, $userId) ?? throw new CourseNotFoundException;

        $this->courses->softDelete($course);

        $this->audit->log('course_scripts.course_deleted', $course, ['course_uuid' => $course->uuid], $causer, 'course_scripts.course');
    }
}
