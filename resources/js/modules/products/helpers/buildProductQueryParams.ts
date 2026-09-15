import type { ProductFilters } from '../types';

/** What a URL query string can carry once serialised. */
export type ProductQueryParams = Record<string, string | number | undefined>;

/**
 * The filter half of `GET /data/admin/products` — shared by the list query
 * and the export URL, so an exported report can never show different rows
 * than the table.
 *
 * Empty facets are dropped rather than sent as empty strings so the query key
 * (and the URL, via `useUrlSyncedFilters`) stay clean.
 */
export function buildProductQueryParams(
    filters: ProductFilters,
): ProductQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status === 'all' ? undefined : filters.status,
        type: filters.type ?? undefined,
        product_status: filters.product_status ?? undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_field: filters.sort_field,
        sort_order: filters.sort_order,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildProductListQueryParams(
    filters: ProductFilters,
): ProductQueryParams {
    return {
        ...buildProductQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
