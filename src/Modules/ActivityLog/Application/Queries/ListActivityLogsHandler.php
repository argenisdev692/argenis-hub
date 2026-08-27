<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Application\Queries;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\ActivityLog\Application\DTOs\ActivityLogData;
use Modules\ActivityLog\Application\DTOs\ActivityLogFilterData;
use Spatie\Activitylog\Models\Activity;

/**
 * Paginated, filtered read over the immutable activity-log trail. Eager-loads
 * `causer` to resolve the actor label without N+1, maps each row through
 * {@see ActivityLogData} and preserves the flat paginator envelope
 * (`->through()`) the frontend `PaginatedResponse<T>` expects.
 */
final readonly class ListActivityLogsHandler
{
    /**
     * @return LengthAwarePaginator<int, ActivityLogData>
     */
    public function handle(ActivityLogFilterData $filters): LengthAwarePaginator
    {
        return $filters
            ->applyTo(Activity::query()->with('causer'))
            ->orderBy('created_at', $filters->normalizedSortDirection())
            ->paginate($filters->normalizedPerPage())
            ->through(ActivityLogData::fromActivity(...));
    }
}
