import type { LeadFilters } from '../types';

/**
 * Filters → the query string `GET /data/admin/lead-scout/leads` and the
 * export endpoint accept. One builder for both, so an exported spreadsheet
 * can never show different rows than the table above it.
 */
export function buildLeadListQueryParams(
    filters: LeadFilters,
): Record<string, string | number | string[]> {
    const params: Record<string, string | number | string[]> = {
        page: filters.page,
        per_page: filters.per_page,
    };

    if (filters.search.trim() !== '') {
        params.search = filters.search.trim();
    }

    if (filters.tier.length > 0) {
        params['tier[]'] = filters.tier;
    }

    if (filters.country.length > 0) {
        params['country[]'] = filters.country.map((country) =>
            country.toUpperCase(),
        );
    }

    if (filters.company_type.length > 0) {
        params['company_type[]'] = filters.company_type;
    }

    if (filters.signal_type.length > 0) {
        params['signal_type[]'] = filters.signal_type;
    }

    if (filters.stage.length > 0) {
        params['stage[]'] = filters.stage;
    }

    if (filters.origin.length > 0) {
        params['origin[]'] = filters.origin;
    }

    if (filters.needs_research !== null) {
        params.needs_research = filters.needs_research ? 1 : 0;
    }

    if (filters.date_from !== null) {
        params.date_from = filters.date_from;
    }

    if (filters.date_to !== null) {
        params.date_to = filters.date_to;
    }

    return params;
}
