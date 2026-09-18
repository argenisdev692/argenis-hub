import type { StudioPostingFilters } from '../types';

/** What a URL query string can carry once serialised (arrays repeat as `key[]`). */
export type StudioQueryParams = Record<
    string,
    string | number | string[] | undefined
>;

/**
 * The filter half of `GET /cv-studio/postings` — the params
 * `StudioPostingFilterData` understands. Single source for the list query
 * and the export URL, so the spreadsheet always matches the screen (sort
 * included: the export streams rows in the order the table shows them).
 */
export function buildPostingQueryParams(
    filters: StudioPostingFilters,
): StudioQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status,
        remote_scope: filters.remote_scope || undefined,
        stages: filters.stages.length > 0 ? filters.stages : undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_field: filters.sort_field,
        sort_order: filters.sort_order,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildPostingListQueryParams(
    filters: StudioPostingFilters,
): StudioQueryParams {
    return {
        ...buildPostingQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
