import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/invoices/admin';
import type { InvoiceFilters, InvoicePage } from '../types';

export function defaultInvoiceFilters(): InvoiceFilters {
    return {
        search: '',
        status: 'all',
        client_uuid: null,
        year: null,
        date_from: null,
        date_to: null,
        page: 1,
        per_page: 15,
    };
}

/**
 * The invoice list, in the one shape the admin page needs.
 *
 * No `sort_field` / `sort_order`: the backend orders every page by
 * `issue_date DESC, sequence DESC` and offers no alternative, because an
 * invoice ledger read in any other order stops being a ledger. Exposing a sort
 * control the API ignores would be worse than not having one.
 */
export function useInvoices() {
    const filters = ref<InvoiceFilters>(defaultInvoiceFilters());

    /**
     * `InvoiceFilterData::$status` only branches on `'active'` or
     * `'suspended'`; "All" is sent as an omitted param rather than the literal
     * string, and empty search / unset bounds are dropped the same way so the
     * query key (and the URL, via `useUrlSyncedFilters`) stay clean.
     */
    const queryParams = computed(() => ({
        ...filters.value,
        status:
            filters.value.status === 'all' ? undefined : filters.value.status,
        search: filters.value.search || undefined,
        client_uuid: filters.value.client_uuid ?? undefined,
        year: filters.value.year ?? undefined,
        date_from: filters.value.date_from ?? undefined,
        date_to: filters.value.date_to ?? undefined,
    }));

    const { data, ...query } = useQuery<InvoicePage>({
        key: () => ['invoices', { ...queryParams.value }],
        query: () =>
            httpJson<InvoicePage>(index.url({ query: queryParams.value })),
        staleTime: 1000 * 60 * 2,
        gcTime: 1000 * 60 * 5,
        // Keep the previous page visible while the next one loads.
        placeholderData: (previousData) => previousData,
    });

    const invoices = computed(() => data.value?.data ?? []);
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

    return { ...query, data, invoices, meta, filters };
}
