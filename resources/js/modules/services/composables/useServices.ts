import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/services/admin';
import { buildServiceListQueryParams } from '../helpers/buildServiceQueryParams';
import type { ServiceFilters, ServicePage } from '../types';

export function defaultServiceFilters(): ServiceFilters {
    return {
        search: '',
        status: 'all',
        date_from: null,
        date_to: null,
        sort_field: 'created_at',
        sort_order: -1,
        page: 1,
        per_page: 15,
    };
}

/**
 * The Services list, in the one shape the admin page needs.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters`
 * ref — see the `defineQuery`/`defineMutation` decision rule in
 * `FRONTEND/SKILL.md` §6.
 */
export function useServices() {
    const filters = ref<ServiceFilters>(defaultServiceFilters());

    /**
     * One builder for the list query AND the export URL (`Index.vue` reuses
     * the filter half), so the two can never drift apart. `page` / `per_page`
     * ride along here; the export drops them by using the filter half only.
     */
    const queryParams = computed(() =>
        buildServiceListQueryParams(filters.value),
    );

    const { data, ...query } = useQuery<ServicePage>({
        key: () => ['services', { ...queryParams.value }],
        query: () =>
            httpJson<ServicePage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const services = computed(() => data.value?.data ?? []);
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

    return { ...query, data, services, meta, filters };
}
