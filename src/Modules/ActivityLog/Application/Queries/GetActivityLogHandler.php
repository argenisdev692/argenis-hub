<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Application\Queries;

use Modules\ActivityLog\Application\DTOs\ActivityLogDetailData;
use Spatie\Activitylog\Models\Activity;

/**
 * Single activity-log entry for the read-only detail screen. Eager-loads the
 * `causer` and `subject` relations so the detail card can label both actors
 * without N+1.
 */
final readonly class GetActivityLogHandler
{
    public function handle(int $id): ActivityLogDetailData
    {
        $activity = Activity::query()
            ->with(['causer', 'subject'])
            ->findOrFail($id);

        return ActivityLogDetailData::fromActivity($activity);
    }
}
