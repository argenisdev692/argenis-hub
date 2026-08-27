<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Infrastructure\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\ActivityLog\Application\DTOs\ActivityLogFilterData;
use Modules\ActivityLog\Application\Queries\GetActivityLogHandler;
use Modules\ActivityLog\Application\Queries\ListActivityLogsHandler;

/**
 * Read-only audit-trail viewer. The trail is immutable: NO create/update/delete
 * routes exist — rows are written by the app and removed only by the scheduled
 * `activity-log:archive` command. Every route is authorized via
 * `permission:*_ACTIVITY_LOGS` middleware (not roles). Controller stays thin:
 * validate → handler → response.
 */
final readonly class ActivityLogController
{
    public function index(Request $request, ActivityLogFilterData $filters, ListActivityLogsHandler $list): InertiaResponse|JsonResponse
    {
        $logs = $list->handle($filters);

        return match (true) {
            $request->expectsJson() => response()->json($logs),
            default => Inertia::render('activity-logs/Index', ['logs' => $logs, 'filters' => $filters]),
        };
    }

    public function show(Request $request, string $activity, GetActivityLogHandler $get): InertiaResponse|JsonResponse
    {
        $log = $get->handle((int) $activity);

        return match (true) {
            $request->expectsJson() => response()->json(['data' => $log]),
            default => Inertia::render('activity-logs/Show', ['log' => $log]),
        };
    }
}
