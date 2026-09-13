import type { VideoEditFilters } from '../types';

/** What a URL query string can carry once serialised. */
export type VideoEditQueryParams = Record<string, string | number | undefined>;

/**
 * The filter half of `GET /data/admin/video-edits` — shared by the list query
 * and the export URL, so an exported report can never show different rows
 * than the table. The server side of that guarantee is `VideoEditFilterData`
 * feeding one `scopeApplyFilters` for both endpoints.
 *
 * Empty facets are dropped rather than sent as empty strings: the backend's
 * `Rule::in` would reject `status=`.
 */
export function buildVideoEditQueryParams(
    filters: VideoEditFilters,
): VideoEditQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status || undefined,
        mode: filters.mode || undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_field: filters.sort_field,
        sort_order: filters.sort_order,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildVideoEditListQueryParams(
    filters: VideoEditFilters,
): VideoEditQueryParams {
    return {
        ...buildVideoEditQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
