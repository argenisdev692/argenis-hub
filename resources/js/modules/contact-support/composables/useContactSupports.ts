import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/contact-supports/admin';
import type { ContactSupportFilters, ContactSupportPage } from '../types';

export function defaultContactSupportFilters(): ContactSupportFilters {
    return {
        search: '',
        status: 'all',
        readed: 'all',
        is_spam: 'all',
        date_from: null,
        date_to: null,
        sort_field: 'created_at',
        sort_order: -1,
        page: 1,
        per_page: 15,
    };
}

/**
 * The support inbox list, in the one shape the admin page needs.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters`
 * ref — see the `defineQuery`/`defineMutation` decision rule in
 * `FRONTEND/SKILL.md` §6.
 */
export function useContactSupports() {
    const filters = ref<ContactSupportFilters>(defaultContactSupportFilters());

    /**
     * `ContactSupportFilterData` branches on `status` (`'active'` / `'deleted'`
     * / empty for "both") and reads `readed` / `is_spam` only when they are a
     * real boolean — so the UI's "All" options are sent as omitted params, not
     * literal strings. Empty search / unset date bounds are dropped the same way
     * so the query key (and the URL, via `useUrlSyncedFilters`) stay clean.
     */
    const queryParams = computed(() => ({
        ...filters.value,
        status:
            filters.value.status === 'all' ? undefined : filters.value.status,
        search: filters.value.search || undefined,
        readed:
            filters.value.readed === 'all'
                ? undefined
                : filters.value.readed === 'read',
        is_spam:
            filters.value.is_spam === 'all'
                ? undefined
                : filters.value.is_spam === 'spam',
        date_from: filters.value.date_from ?? undefined,
        date_to: filters.value.date_to ?? undefined,
    }));

    const { data, ...query } = useQuery<ContactSupportPage>({
        key: () => ['contact-supports', { ...queryParams.value }],
        query: () =>
            httpJson<ContactSupportPage>(
                index.url({ query: queryParams.value }),
            ),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads — no empty-table flash.
        placeholderData: (previousData) => previousData,
    });

    const contactSupports = computed(() => data.value?.data ?? []);
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

    return { ...query, data, contactSupports, meta, filters };
}
