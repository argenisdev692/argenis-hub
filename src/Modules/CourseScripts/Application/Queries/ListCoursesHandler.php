<?php

declare(strict_types=1);

namespace Modules\CourseScripts\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\CourseScripts\Application\DTOs\CourseFilterData;
use Modules\CourseScripts\Application\DTOs\CourseListItemData;
use Modules\CourseScripts\Domain\Ports\CourseRepositoryPort;
use Modules\CourseScripts\Infrastructure\Persistence\Eloquent\Models\CourseEloquentModel;

/**
 * The owner's courses, paginated and filtered (US-10).
 */
final readonly class ListCoursesHandler
{
    public function __construct(
        private CourseRepositoryPort $courses,
    ) {}

    /**
     * @return LengthAwarePaginator<int, CourseListItemData>
     */
    public function handle(CourseFilterData $filters, int $userId): LengthAwarePaginator
    {
        $page = $this->courses->paginateOwned($userId, $filters);

        $page->setCollection($page->getCollection()->map(
            static fn (CourseEloquentModel $course): CourseListItemData => CourseListItemData::fromModel($course),
        ));

        return $page;
    }
}
