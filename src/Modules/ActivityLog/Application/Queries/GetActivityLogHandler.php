<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Application\Queries;

use Modules\ActivityLog\Application\DTOs\ActivityLogDetailData;
use Spatie\Activitylog\Models\Activity;

/**
 * Single activity-log entry for the read-only detail screen. Eager-loads the
 * `causer` relation so {@see ActivityLogDetailData} can label the actor without
 * N+1. The `subject` relation is deliberately NOT loaded: the projection exposes
 * only the stored `subject_type` / `subject_id` columns, so hydrating the
 * polymorphic target would cost an extra query per morph type and return
 * nothing the response uses.
 */
final readonly class GetActivityLogHandler
{
    public function handle(int $id): ActivityLogDetailData
    {
        $activity = Activity::query()
            ->with('causer')
            ->findOrFail($id);

        return ActivityLogDetailData::fromActivity($activity);
    }
}
