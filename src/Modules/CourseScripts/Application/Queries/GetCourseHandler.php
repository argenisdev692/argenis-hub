<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Queries;

use Modules\CourseScripts\Application\DTOs\CourseDetailData;
use Modules\CourseScripts\Domain\Exceptions\CourseNotFoundException;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;

final readonly class GetCourseHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
    ) {}

    /**
     * @throws CourseNotFoundException
     */
    public function handle(string $uuid, int $userId): CourseDetailData
    {
        $course = $this->courses->findOwnedWithStructure($uuid, $userId) ?? throw new CourseNotFoundException;

        return CourseDetailData::fromModel($course);
    }
}
