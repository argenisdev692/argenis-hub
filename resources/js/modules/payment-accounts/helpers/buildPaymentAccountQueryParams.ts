import type { PaymentAccountFilters } from '../types';

/** What a URL query string can carry once serialised. */
export type PaymentAccountQueryParams = Record<
    string,
    string | number | undefined
>;

/**
 * The filter half of `GET /data/admin/payment-accounts` — shared by the list
 * query and the export URL, so an exported report can never show different
 * rows than the table.
 *
 * Empty facets are dropped rather than sent as empty strings so the query key
 * (and the URL, via `useUrlSyncedFilters`) stay clean.
 */
export function buildPaymentAccountQueryParams(
    filters: PaymentAccountFilters,
): PaymentAccountQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status === 'all' ? undefined : filters.status,
        method: filters.method ?? undefined,
        currency: filters.currency ?? undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_field: filters.sort_field,
        sort_order: filters.sort_order,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildPaymentAccountListQueryParams(
    filters: PaymentAccountFilters,
): PaymentAccountQueryParams {
    return {
        ...buildPaymentAccountQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
