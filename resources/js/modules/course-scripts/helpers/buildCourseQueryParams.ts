import type { CourseFilters } from '../types';

export type CourseQueryParams = Record<string, string | number | undefined>;

/**
 * The filter half of `GET /course-scripts` — the single source read by both the
 * list query and the export menu, so an exported file always holds the rows on
 * screen (`CourseExportController` re-applies the same `CourseFilterData`).
 *
 * Empty facets are dropped: `CourseFilterData` treats a missing param as "no
 * filter", and it keeps the query key and the URL free of noise.
 */
export function buildCourseQueryParams(
    filters: CourseFilters,
): CourseQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status || undefined,
        trashed: filters.trashed,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
        sort_field: filters.sort_field,
        sort_order: filters.sort_order,
    };
}

/** The filter params plus paging, which the export endpoint ignores. */
export function buildCourseListQueryParams(
    filters: CourseFilters,
): CourseQueryParams {
    return {
        ...buildCourseQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
