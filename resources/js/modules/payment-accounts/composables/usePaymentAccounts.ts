import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/payment-accounts/admin';
import { buildPaymentAccountListQueryParams } from '../helpers/buildPaymentAccountQueryParams';
import type { PaymentAccountFilters, PaymentAccountPage } from '../types';

export function defaultPaymentAccountFilters(): PaymentAccountFilters {
    return {
        search: '',
        status: 'all',
        method: null,
        currency: null,
        date_from: null,
        date_to: null,
        sort_field: 'created_at',
        sort_order: -1,
        page: 1,
        per_page: 15,
    };
}

/**
 * The settlement-rails list, in the one shape the admin page needs.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters` ref.
 */
export function usePaymentAccounts() {
    const filters = ref<PaymentAccountFilters>(defaultPaymentAccountFilters());

    /**
     * `PaymentAccountFilterData::$status` only branches on `'active'`,
     * `'deleted'` or empty/`null` (meaning "both") — the UI's "All" option is
     * sent as an omitted param rather than the literal string, and empty
     * search / unset bounds are dropped the same way so the query key (and the
     * URL, via `useUrlSyncedFilters`) stay clean.
     */
    /**
     * One builder for the list query AND the export URL (`Index.vue` reuses
     * the filter half), so the two can never drift apart. `page` / `per_page`
     * ride along here; the export drops them by using the filter half only.
     */
    const queryParams = computed(() =>
        buildPaymentAccountListQueryParams(filters.value),
    );

    const { data, ...query } = useQuery<PaymentAccountPage>({
        key: () => ['payment-accounts', { ...queryParams.value }],
        query: () =>
            httpJson<PaymentAccountPage>(
                index.url({ query: queryParams.value }),
            ),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads.
        placeholderData: (previousData) => previousData,
    });

    const accounts = computed(() => data.value?.data ?? []);
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

    return { ...query, data, accounts, meta, filters };
}
