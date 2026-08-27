<?php

declare(strict_types=1);

namespace Modules\ActivityLog\Infrastructure\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\ActivityLog\Application\DTOs\ActivityLogFilterData;
use Modules\ActivityLog\Application\Queries\GetActivityLogHandler;
use Modules\ActivityLog\Application\Queries\ListActivityLogsHandler;

/**
 * Read-only audit trail for Sanctum-authenticated (mobile) clients. Reuses the
 * exact same query handlers and {@see ActivityLogFilterData} contract as the
 * web/Inertia controller. Authorization is checked on the model
 * (`hasPermissionTo`) rather than the `permission:` middleware, which is
 * guard-safe under the `sanctum` guard. Documented by Scramble via return types
 * + `auth:sanctum` detection — no manual annotations.
 */
final readonly class ActivityLogApiController
{
    /**
     * List activity log entries.
     *
     * Returns a paginated, filtered list of audit-trail entries, newest first.
     * Accepts `search`, `event`, `log_name`, `causer_id`, `date_from`, `date_to`,
     * `sort_direction` and `per_page` (capped at 100 to bound resource
     * consumption — OWASP API4).
     */
    public function index(Request $request, ActivityLogFilterData $filters, ListActivityLogsHandler $list): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ANY_ACTIVITY_LOGS'), 403);

        return response()->json($list->handle($filters));
    }

    /**
     * Show an activity log entry.
     *
     * Returns a single audit-trail entry with its full property and
     * attribute-change payloads.
     */
    public function show(Request $request, string $id, GetActivityLogHandler $get): JsonResponse
    {
        abort_unless((bool) $request->user()?->hasPermissionTo('VIEW_ACTIVITY_LOGS'), 403);

        return response()->json(['data' => $get->handle((int) $id)]);
    }
}
