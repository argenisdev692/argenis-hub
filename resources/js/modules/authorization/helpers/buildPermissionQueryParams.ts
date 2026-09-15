import type { PermissionFilters, PermissionQueryParams } from '../types';

/**
 * The filter half of `GET /permissions` and `GET /permissions/export` —
 * shared by the catalogue query and the export URL, so an exported report can
 * never show different rows than the table. Same contract as
 * {@see buildRoleQueryParams}: `status` is always sent, everything else is
 * dropped when empty.
 */
export function buildPermissionQueryParams(
    filters: PermissionFilters,
): PermissionQueryParams {
    return {
        search: filters.search || undefined,
        status: filters.status,
        date_from: filters.date_from ?? undefined,
        date_to: filters.date_to ?? undefined,
    };
}

/** The filter params plus the paging the export endpoint has no use for. */
export function buildPermissionListQueryParams(
    filters: PermissionFilters,
): PermissionQueryParams {
    return {
        ...buildPermissionQueryParams(filters),
        page: filters.page,
        per_page: filters.per_page,
    };
}
