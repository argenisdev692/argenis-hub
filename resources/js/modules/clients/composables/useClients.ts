import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/clients/admin';
import { buildClientQueryParams } from '../helpers/buildClientQueryParams';
import type { ClientFilters, ClientPage } from '../types';

export function defaultClientFilters(): ClientFilters {
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
 * The Clients list, in the one shape the admin page needs.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters`
 * ref — see the `defineQuery`/`defineMutation` decision rule in
 * `FRONTEND/SKILL.md` §6.
 */
export function useClients() {
    const filters = ref<ClientFilters>(defaultClientFilters());

    /**
     * Filter half shared with the export menu — see
     * `buildClientQueryParams` — plus pagination, which is query-only and
     * must never reach an export URL.
     */
    const queryParams = computed(() => ({
        ...buildClientQueryParams(filters.value),
        page: filters.value.page,
        per_page: filters.value.per_page,
    }));

    const { data, ...query } = useQuery<ClientPage>({
        key: () => ['clients', { ...queryParams.value }],
        query: () =>
            httpJson<ClientPage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const clients = computed(() => data.value?.data ?? []);
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

    return { ...query, data, clients, meta, filters };
}
