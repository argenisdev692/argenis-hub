import type { ServiceFilters } from '../types';

/** What a URL query string can carry once serialised. */
export type ServiceQueryParams = Record<string, string | number | undefined>;

/**
 * The filter half of `GET /data/admin/services` — shared by the list query
 * and the export URL, so an exported report can never show different rows
 * than the table. The server side of that guarantee is `ServiceFilterData`
 * feeding one `scopeApplyFilters` for both endpoints.
 *
 * `ServiceFilterData::status` only branches on `'active'`, `'deleted'` or
 * empty/`null` (meaning "both") — there is no `'all'` case on the backend, so
 * the UI's "All" option is sent as an omitted param instead of the literal
 * string. Empty search / unset date bounds are dropped the same way so the
 * query key (and the URL, via `useUrlSyncedFilters`) stay clean.
 */
export function buildServiceQueryParams(
    filters: ServiceFilters,
): ServiceQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status === 'all' ? undefined : filters.status,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_field: filters.sort_field,
        sort_order: filters.sort_order,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildServiceListQueryParams(
    filters: ServiceFilters,
): ServiceQueryParams {
    return {
        ...buildServiceQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
