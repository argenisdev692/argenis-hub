import type { RoleFilters, RoleQueryParams } from '../types';

/**
 * The filter half of `GET /roles` and `GET /roles/export` — shared by the
 * list query and the export URL, so an exported report can never show
 * different rows than the table.
 *
 * Empty search and unset date bounds are dropped rather than sent blank, so
 * the query key (and the URL, via `useUrlSyncedFilters`) stay clean.
 * `status` is always sent: unlike the other modules there is no "both" case
 * to express by omitting it — the backend's default is `active`.
 *
 * Typed against the generated filter DTO, so a field renamed in
 * `RoleFilterData` fails the build here instead of silently never applying.
 */
export function buildRoleQueryParams(filters: RoleFilters): RoleQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildRoleListQueryParams(
    filters: RoleFilters,
): RoleQueryParams {
    return {
        ...buildRoleQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
