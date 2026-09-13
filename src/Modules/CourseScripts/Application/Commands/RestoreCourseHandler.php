<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Shared\Domain\Ports\AuditPort;

/**
 * Brings a soft-deleted course back from the recovery window (US-11). A course
 * already pruned — or another user's — is indistinguishable from a missing one.
 */
final readonly class RestoreCourseHandler
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
        $course = $this->courses->findOwnedTrashed($uuid, $userId) ?? throw new CourseNotFoundException;

        $this->courses->restore($course);

        $this->audit->log('course_scripts.course_restored', $course, ['course_uuid' => $course->uuid], $causer, 'course_scripts.course');
    }
}
