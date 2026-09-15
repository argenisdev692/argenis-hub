import type { ActivityLogFilters } from '../types';

/**
 * The one projection from UI filter state to wire params.
 *
 * Empty text axes are dropped so the backend `when(filled(...))` guards see an
 * absent value, and so the query key (and the URL, via `useUrlSyncedFilters`)
 * stay short.
 *
 * Pagination (`page`, `per_page`) is deliberately NOT part of this projection:
 * the list query appends it, the export URL must never carry it (an export is
 * the whole filtered set, not one page). Both `useActivityLogs()` and the
 * `<DataTableExportMenu>` params read the filter half through here, so the
 * exported rows always match the on-screen list. Adding a filter? Extend this
 * function, not the two call sites.
 */
export function buildActivityLogQueryParams(
    filters: ActivityLogFilters,
): Record<string, string | number | undefined> {
    return {
        search: filters.search || undefined,
        event: filters.event || undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_direction: filters.sort_direction,
    };
}
