import type { CvFilters } from '../types';

/** What a URL query string can carry once serialised. */
export type CvQueryParams = Record<string, string | number | undefined>;

/**
 * The filter half of `GET /cvs` — the params `CvFilterData` understands.
 *
 * The single source both the list query (`useCvs`) and the export URL
 * (`DataTableExportMenu`) read from, so the rows in the spreadsheet always
 * match the rows on screen. `CvExportController` re-uses the very same
 * `CvFilterData` + `scopeApplyFilters` on the server, which is the other half
 * of that guarantee.
 *
 * Empty facets are dropped rather than sent as empty strings: `CvFilterData`
 * treats `null` as "no filter", and keeping the params out entirely also keeps
 * the query key — and the URL, via `useUrlSyncedFilters` — free of noise that
 * means nothing.
 *
 * `status` is always sent. Unlike the other modules there is no "all" option to
 * translate away here, because the backend cannot serve one (see
 * `CvStatusFilter`).
 */
export function buildCvQueryParams(filters: CvFilters): CvQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status,
        niche: filters.niche || undefined,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildCvListQueryParams(filters: CvFilters): CvQueryParams {
    return {
        ...buildCvQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
