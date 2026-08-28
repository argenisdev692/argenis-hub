import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/clients/admin';
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
     * `ClientFilterData::status` only branches on `'active'`, `'deleted'` or
     * empty/`null` (meaning "both") — there is no `'all'` case on the backend,
     * so the UI's "All" option is sent as an omitted param instead of the
     * literal string. Empty search / unset date bounds are dropped the same way
     * so the query key (and the URL, via `useUrlSyncedFilters`) stay clean.
     */
    const queryParams = computed(() => ({
        ...filters.value,
        status:
            filters.value.status === 'all' ? undefined : filters.value.status,
        search: filters.value.search || undefined,
        date_from: filters.value.date_from ?? undefined,
        date_to: filters.value.date_to ?? undefined,
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
