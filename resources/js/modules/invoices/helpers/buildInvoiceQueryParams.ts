import type { InvoiceFilters } from '../types';

/** What a URL query string can carry once serialised. */
export type InvoiceQueryParams = Record<string, string | number | undefined>;

/**
 * The filter half of `GET /data/admin/invoices` — the params
 * `InvoiceFilterData` understands.
 *
 * The single source both the list query (`useInvoices`) and the export URL
 * (`DataTableExportMenu`) read from, so the rows in the spreadsheet always
 * match the rows on screen. `InvoiceExportController` re-uses the very same
 * `InvoiceFilterData` on the server, which is the other half of that guarantee.
 *
 * Empty facets are dropped rather than sent as empty strings: `InvoiceFilterData`
 * treats `null` as "no filter", and keeping the params out entirely also keeps
 * the query key — and the URL, via `useUrlSyncedFilters` — free of noise that
 * means nothing.
 *
 * `status` is always sent, `'all'` included: on the server that value is the
 * only one that lifts the `SoftDeletes` scope and returns live and suspended
 * invoices side by side. Omitting it would fall back to active-only, which is
 * not what the operator picked.
 *
 * `payment_status: 'all'` is the opposite case — there the server has no third
 * state to select, so "no opinion" really is an omitted param.
 */
export function buildInvoiceQueryParams(
    filters: InvoiceFilters,
): InvoiceQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status,
        payment_status:
            filters.payment_status === 'all'
                ? undefined
                : filters.payment_status,
        client_uuid: filters.client_uuid ?? undefined,
        year: filters.year ?? undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildInvoiceListQueryParams(
    filters: InvoiceFilters,
): InvoiceQueryParams {
    return {
        ...buildInvoiceQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
