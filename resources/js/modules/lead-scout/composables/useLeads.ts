import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/lead-scout/leads';
import { buildLeadListQueryParams } from '../helpers/buildLeadQueryParams';
import type { LeadFilters, LeadPage } from '../types';

/** The key every LeadScout mutation invalidates. */
export const LEADS_KEY = ['lead-scout', 'leads'];

export function defaultLeadFilters(): LeadFilters {
    return {
        tier: [],
        country: [],
        company_type: [],
        signal_type: [],
        stage: [],
        origin: [],
        needs_research: null,
        search: '',
        date_from: null,
        date_to: null,
        page: 1,
        per_page: 15,
    };
}

/**
 * The prioritized bandeja. Server state lives here (Pinia Colada), never
 * mirrored into a store — `Index.vue` is the only caller, so a local
 * `filters` ref is enough (see the `defineQuery` decision rule).
 */
export function useLeads() {
    const filters = ref<LeadFilters>(defaultLeadFilters());

    const queryParams = computed(() => buildLeadListQueryParams(filters.value));

    const { data, ...query } = useQuery<LeadPage>({
        key: () => ['lead-scout', 'leads', { ...queryParams.value }],
        query: () =>
            httpJson<LeadPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        placeholderData: (previousData) => previousData,
    });

    const leads = computed(() => data.value?.data ?? []);
    const meta = computed<PaginationMeta | undefined>(() => {
        if (data.value === undefined) {
            return undefined;
        }

        const { current_page, per_page, total } = data.value.meta;
        const from = total === 0 ? null : (current_page - 1) * per_page + 1;

        return {
            current_page,
            per_page,
            total,
            last_page: Math.max(1, Math.ceil(total / per_page)),
            from,
            to: from === null ? null : from + data.value.data.length - 1,
        };
    });

    return { ...query, data, leads, meta, filters };
}
