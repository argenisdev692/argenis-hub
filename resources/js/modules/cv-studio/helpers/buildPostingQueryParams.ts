import type { StudioPostingFilters } from '../types';

/** What a URL query string can carry once serialised. */
export type StudioQueryParams = Record<string, string | number | undefined>;

/**
 * The filter half of `GET /cv-studio/postings` — the params
 * `StudioPostingFilterData` understands. Single source for the list query
 * and the export URL, so the spreadsheet always matches the screen.
 */
export function buildPostingQueryParams(
    filters: StudioPostingFilters,
): StudioQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status,
        remote_scope: filters.remote_scope || undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
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
