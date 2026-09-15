import type { BackupFilters } from '../types';

/**
 * The one projection from UI filter state to wire params.
 *
 * `scopeApplyFilters` only branches on a concrete status (`running` /
 * `completed` / `failed`) — there is no `'all'` case — so the UI's "All"
 * option is sent as an omitted param. Empty search / unset date bounds are
 * dropped the same way so the query key (and the URL, via
 * `useUrlSyncedFilters`) stay short.
 *
 * Pagination (`page`, `per_page`) is deliberately NOT part of this projection:
 * the list query appends it, the export URL must never carry it (an export is
 * the whole filtered set, not one page). Both `useBackups()` and the
 * `<DataTableExportMenu>` params read the filter half through here, so the
 * exported rows always match the on-screen list. Adding a filter? Extend this
 * function, not the two call sites.
 */
export function buildBackupQueryParams(
    filters: BackupFilters,
): Record<string, string | number | undefined> {
    return {
        search: filters.search || undefined,
        status: filters.status === 'all' ? undefined : filters.status,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_field: filters.sort_field,
        sort_order: filters.sort_order,
    };
}
