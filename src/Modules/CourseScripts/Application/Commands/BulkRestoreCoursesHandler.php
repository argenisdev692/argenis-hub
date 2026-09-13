<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Domain\Ports\AuditPort;

/**
 * Restores a selection of the owner's soft-deleted courses (US-11).
 * Authorization (permission:RESTORE_COURSE_SCRIPTS) is enforced at the route;
 * ownership here.
 */
final readonly class BulkRestoreCoursesHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private AuditPort $audit,
    ) {}

    /**
     * @return int the number of courses actually restored
     */
    #[\NoDiscard]
    public function handle(BulkUuidsData $data, int $userId, ?object $causer = null): int
    {
        $restored = $this->courses->bulkRestoreOwned(array_values($data->uuids), $userId);

        foreach ($restored as $course) {
            $this->audit->log('course_scripts.course_restored', $course, ['course_uuid' => $course->uuid, 'bulk' => true], $causer, 'course_scripts.course');
        }

        return count($restored);
    }
}
