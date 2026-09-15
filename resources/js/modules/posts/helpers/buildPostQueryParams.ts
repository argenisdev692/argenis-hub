import type { PostFilters } from '../types';

/**
 * The one projection from UI filter state to wire params.
 *
 * `PostFilterData::status` only accepts `draft`, `published`, `scheduled` or
 * `suspended` — there is no `'all'` case, so the UI's "All" option is sent as
 * an omitted param. Empty search, unset category and unset date bounds drop
 * the same way, keeping both the Pinia Colada query key and the export URL
 * free of noise that means nothing.
 *
 * Pagination (`page`, `per_page`) is deliberately NOT part of this projection:
 * the list query appends it, the export URL must never carry it (an export is
 * the whole filtered set, not one page). Both `usePosts()` and the
 * `<DataTableExportMenu>` params read the filter half through here, so the
 * exported rows always match the on-screen list. Adding a filter? Extend this
 * function, not the two call sites.
 */
export function buildPostQueryParams(
    filters: PostFilters,
): Record<string, string | number | undefined> {
    return {
        search: filters.search || undefined,
        status: filters.status === 'all' ? undefined : filters.status,
        category_uuid: filters.category_uuid ?? undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_field: filters.sort_field,
        sort_order: filters.sort_order,
    };
}
