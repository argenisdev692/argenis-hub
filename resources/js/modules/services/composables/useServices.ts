import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/services/admin';
import type { ServiceFilters, ServicePage } from '../types';

function defaultFilters(): ServiceFilters {
    return {
        search: '',
        status: 'all',
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
    const filters = ref<ServiceFilters>(defaultFilters());

    /**
     * `ServiceFilterData::status` only branches on `'active'`, `'deleted'`
     * or empty/`null` (meaning "both") — there is no `'all'` case on the
     * backend, so the UI's "All" option is sent as an omitted param instead
     * of the literal string.
     */
    const queryParams = computed(() => ({
        ...filters.value,
        status:
            filters.value.status === 'all' ? undefined : filters.value.status,
    }));

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
