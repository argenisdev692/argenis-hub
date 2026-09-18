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
    const meta = computed<PaginationMeta | undefined>(() =>
        data.value
            ? {
                  current_page: data.value.current_page,
                  last_page: data.value.last_page,
                  per_page: data.value.per_page,
                  from: data.value.from,
                  to: data.value.to,
                  total: data.value.total,
              }
            : undefined,
    );

    return { ...query, data, leads, meta, filters };
}
