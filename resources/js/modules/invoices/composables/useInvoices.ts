import { useQuery } from '@pinia/colada';
import { computed, ref } from 'vue';
import type { PaginationMeta } from '@/common/table';
import { httpJson } from '@/lib/http';
import { index } from '@/routes/invoices/admin';
import { buildInvoiceListQueryParams } from '../helpers/buildInvoiceQueryParams';
import type {
    InvoiceFilters,
    InvoicePage,
    InvoicePaymentFilter,
    InvoiceStatusFilter,
} from '../types';

/** The key every invoice mutation invalidates. */
export const INVOICES_KEY = ['invoices'];

export function defaultInvoiceFilters(): InvoiceFilters {
    return {
        search: '',
        status: 'all',
        payment_status: 'all',
        client_uuid: null,
        year: null,
        date_from: null,
        date_to: null,
        page: 1,
        per_page: 15,
    };
}

const STATUS_FILTERS: readonly InvoiceStatusFilter[] = [
    'all',
    'active',
    'suspended',
];

const PAYMENT_FILTERS: readonly InvoicePaymentFilter[] = [
    'all',
    'paid',
    'unpaid',
];

/**
 * Narrowing guards for the toolbar selects, which hand back the wide
 * `FilterSelectValue` — a checked narrowing instead of an `as` cast (§13).
 */
export function isInvoiceStatusFilter(
    value: unknown,
): value is InvoiceStatusFilter {
    return (
        typeof value === 'string' &&
        (STATUS_FILTERS as readonly string[]).includes(value)
    );
}

export function isInvoicePaymentFilter(
    value: unknown,
): value is InvoicePaymentFilter {
    return (
        typeof value === 'string' &&
        (PAYMENT_FILTERS as readonly string[]).includes(value)
    );
}

/**
 * The invoice list, in the one shape the admin page needs.
 *
 * No `sort_field` / `sort_order`: the backend orders every page by
 * `issue_date DESC, sequence DESC` and offers no alternative, because an
 * invoice ledger read in any other order stops being a ledger. Exposing a sort
 * control the API ignores would be worse than not having one.
 *
 * A plain composable, not `defineQuery`: `Index.vue` is the only caller, so
 * there is nothing to de-synchronise by giving it its own local `filters` ref.
 *
 * The query string is built by `buildInvoiceListQueryParams`, the same helper
 * the export menu calls — which is what stops an exported spreadsheet from
 * showing a different set of rows than the table above it.
 */
export function useInvoices() {
    const filters = ref<InvoiceFilters>(defaultInvoiceFilters());

    const queryParams = computed(() =>
        buildInvoiceListQueryParams(filters.value),
    );

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
