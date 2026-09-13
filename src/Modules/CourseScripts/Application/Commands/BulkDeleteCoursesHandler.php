<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Shared\Application\DTOs\BulkUuidsData;
use Shared\Domain\Ports\AuditPort;

/**
 * Soft-deletes a selection of the owner's courses (US-11). Authorization
 * (permission:DELETE_COURSE_SCRIPTS) is enforced at the route; ownership here.
 */
final readonly class BulkDeleteCoursesHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
        private AuditPort $audit,
    ) {}

    /**
     * @return int the number of courses actually deleted
     */
    #[\NoDiscard]
    public function handle(BulkUuidsData $data, int $userId, ?object $causer = null): int
    {
        $deleted = $this->courses->bulkSoftDeleteOwned(array_values($data->uuids), $userId);

        foreach ($deleted as $course) {
            $this->audit->log('course_scripts.course_deleted', $course, ['course_uuid' => $course->uuid, 'bulk' => true], $causer, 'course_scripts.course');
        }

        return count($deleted);
    }
}
