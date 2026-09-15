import type { BlogCategoryFilters } from '../types';

/**
 * The one projection from UI filter state to wire params.
 *
 * An empty search or an unset date bound is dropped rather than sent as an
 * empty string: `BlogCategoryFilterData` treats `null` as "no filter", and
 * keeping the params out entirely also keeps the query key — and the URL, via
 * `useUrlSyncedFilters` — free of noise that means nothing.
 *
 * `status` is always sent. Unlike the other modules there is no "all" option
 * to translate away here, because the backend cannot serve one (see
 * `BlogCategoryStatusFilter`).
 *
 * Pagination (`page`, `per_page`) is deliberately NOT part of this projection:
 * the list query appends it, the export URL must never carry it (an export is
 * the whole filtered set, not one page). Both `useBlogCategories()` and the
 * `<DataTableExportMenu>` params read the filter half through here, so the
 * exported rows always match the on-screen list. Adding a filter? Extend this
 * function, not the two call sites.
 */
export function buildBlogCategoryQueryParams(
    filters: BlogCategoryFilters,
): Record<string, string | number | undefined> {
    return {
        search: filters.search || undefined,
        status: filters.status,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
    };
}
