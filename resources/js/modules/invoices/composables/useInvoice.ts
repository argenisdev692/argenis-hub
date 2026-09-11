import { useQuery } from '@pinia/colada';
import type { MaybeRefOrGetter } from 'vue';
import { computed, toValue } from 'vue';
import { httpJson } from '@/lib/http';
import { show } from '@/routes/invoices/admin';
import type { InvoiceDetail } from '../types';

/** The cache key for one invoice's detail record. */
export function invoiceKey(uuid: string): string[] {
    return ['invoice', uuid];
}

/**
 * One invoice with its line items, for the detail dialog and to seed the edit
 * form.
 *
 * A second request rather than a reuse of the list row, because the two are not
 * the same record: `InvoiceListItemData` carries no `items`, no tax breakdown,
 * no payment snapshot and no notes — the columns a ledger table has no room for
 * are exactly the ones a detail view exists to show.
 *
 * `uuid` is a getter so one instance serves every row: the dialog writes the
 * selected uuid, the key changes, and Pinia Colada fetches (or serves from
 * cache) without the caller tearing the query down and standing a new one up.
 * `enabled` keeps it idle while nothing is selected.
 */
export function useInvoice(uuid: MaybeRefOrGetter<string | null>) {
    const { data, ...query } = useQuery<InvoiceDetail>({
        key: () => invoiceKey(toValue(uuid) ?? 'none'),
        query: () => httpJson<InvoiceDetail>(show.url(toValue(uuid) ?? '')),
        enabled: () => toValue(uuid) !== null,
        // Longer than the list's two minutes: a saved invoice is immutable in
        // practice, and every write that could change it invalidates this key
        // by hand from `useInvoiceMutations`.
        staleTime: 1000 * 60 * 5,
        gcTime: 1000 * 60 * 10,
    });

    const invoice = computed<InvoiceDetail | null>(() => data.value ?? null);
    const items = computed(() => data.value?.items ?? []);

    return { ...query, data, invoice, items };
}
