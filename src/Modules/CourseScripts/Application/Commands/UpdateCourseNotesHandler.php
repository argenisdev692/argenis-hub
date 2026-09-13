<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Commands;

use Modules\CourseScripts\Application\DTOs\UpdateCourseNotesData;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;

final readonly class UpdateCourseNotesHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
    ) {}

    /**
     * @throws CourseNotFoundException
     */
    public function handle(string $courseUuid, UpdateCourseNotesData $data, int $userId): void
    {
        $course = $this->courses->findOwned($courseUuid, $userId) ?? throw new CourseNotFoundException;

        $notes = $data->courseNotes === null || trim($data->courseNotes) === '' ? null : trim($data->courseNotes);

        $this->courses->updateCourseNotes($course, $notes);
    }
}
