import type { ContactSupportFilters } from '../types';

/** What a URL query string can carry once serialised. */
export type ContactSupportQueryParams = Record<
    string,
    string | number | boolean | undefined
>;

/**
 * The filter half of `GET /data/admin/contact-supports` — shared by the list
 * query and the export URL, so an exported report can never show different
 * rows than the table. The server side of that guarantee is
 * `ContactSupportFilterData` feeding one `applyFilters()` for both endpoints.
 *
 * `ContactSupportFilterData` branches on `status` (`'active'` / `'deleted'` /
 * empty for "both") and reads `readed` / `is_spam` only when they are a real
 * boolean — so the UI's "All" options are sent as omitted params, not literal
 * strings. Empty search / unset date bounds are dropped the same way so the
 * query key (and the URL, via `useUrlSyncedFilters`) stay clean.
 */
export function buildContactSupportQueryParams(
    filters: ContactSupportFilters,
): ContactSupportQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status === 'all' ? undefined : filters.status,
        readed:
            filters.readed === 'all' ? undefined : filters.readed === 'read',
        is_spam:
            filters.is_spam === 'all' ? undefined : filters.is_spam === 'spam',
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_field: filters.sort_field,
        sort_order: filters.sort_order,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildContactSupportListQueryParams(
    filters: ContactSupportFilters,
): ContactSupportQueryParams {
    return {
        ...buildContactSupportQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
