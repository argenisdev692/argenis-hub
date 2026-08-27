/**
 * ActivityLog module types.
 *
 * Aliases over the generated declarations, never redefinitions.
 * `resources/js/generated/generated.d.ts` is produced by
 * `php artisan typescript:transform` from the Spatie `Data` classes, so these
 * names track the backend automatically; writing the same shape out by hand is
 * how a renamed column becomes a runtime `undefined` instead of a build error.
 *
 * The trail is read-only: there is no write payload type here, and never will
 * be — the backend exposes only `index` / `show` / `export`.
 */

import type { z } from 'zod';
import type { activityLogFilterSchema } from './schemas/activityLogFilterSchema';

/** One row of the audit trail, as the list endpoint serializes it. */
export type ActivityLogEntry =
    Modules.ActivityLog.Application.DTOs.ActivityLogData;

/**
 * The shared `DataTable` is generic over `{ uuid: string }`, but the trail is
 * keyed by an auto-increment `id`. `useActivityLogs` derives a stable string
 * `uuid` from it so rows can be keyed and the detail link built without a
 * second lookup.
 */
export type ActivityLogRow = ActivityLogEntry & { uuid: string };

/**
 * The detail projection, with the two free-form JSON blobs narrowed from the
 * generated `Record<string, any>` to `unknown` values — the Show page treats
 * every entry as opaque and stringifies it, so `any` would only disable checks
 * with nothing gained.
 */
export type ActivityLogDetail = Omit<
    Modules.ActivityLog.Application.DTOs.ActivityLogDetailData,
    'properties' | 'attribute_changes'
> & {
    readonly properties: Record<string, unknown> | null;
    readonly attribute_changes: Record<string, unknown> | null;
};

/**
 * One page of the trail, exactly as `ActivityLogController::index()` serializes
 * it: `response()->json($paginator)` emits Laravel's own flat
 * `LengthAwarePaginator::toArray()` shape, not Inertia's `{ data, meta }` one —
 * same reasoning as `ServicePage`.
 */
export type ActivityLogPage = {
    data: ActivityLogEntry[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    first_page_url: string;
    last_page_url: string;
    next_page_url: string | null;
    prev_page_url: string | null;
    path: string;
    links: { url: string | null; label: string; active: boolean }[];
};

/**
 * The UI filter state. Its shape and its runtime coercion both come from the
 * Zod schema (single source of truth), so this is just `z.infer` of it.
 */
export type ActivityLogFilters = z.infer<typeof activityLogFilterSchema>;

/** The lifecycle events `spatie/laravel-activitylog` records. */
export type ActivityLogEventName =
    'created' | 'updated' | 'deleted' | 'restored';
