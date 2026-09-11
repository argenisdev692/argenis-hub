import { useQuery } from '@pinia/colada';
import { computed, toValue } from 'vue';
import type { MaybeRefOrGetter } from 'vue';
import { httpJson } from '@/lib/http';
import { formOptions } from '@/routes/invoices/admin';
import type { InvoiceFormOptions, InvoicePaymentAccountOption } from '../types';

/**
 * The catalogs the create/edit form needs — clients, services, published
 * products, settlement rails and the enum vocabularies — in one request.
 *
 * Fetched once and cached for the session rather than per dialog open: the
 * catalog changes far less often than the operator opens the form, and both
 * `useProductMutations` and `usePaymentAccountMutations` invalidate this key
 * when they change something it contains.
 */
export function useInvoiceFormOptions(
    /**
     * Gates the request. The form dialog stays mounted for the life of the
     * index page so it can animate open, so without this the catalog would be
     * fetched on every page load — including for the operators who only ever
     * read the ledger. Passing `() => open.value` defers it to the first open;
     * the long `gcTime` makes every open after that instant.
     */
    enabled: MaybeRefOrGetter<boolean> = true,
) {
    const { data, ...query } = useQuery<InvoiceFormOptions>({
        key: () => ['invoice-form-options'],
        query: () => httpJson<InvoiceFormOptions>(formOptions.url()),
        enabled: () => toValue(enabled),
        staleTime: 1000 * 60 * 5,
        gcTime: 1000 * 60 * 15,
    });

    const clients = computed(() => data.value?.clients ?? []);
    const services = computed(() => data.value?.services ?? []);
    const products = computed(() => data.value?.products ?? []);
    const paymentAccounts = computed(() => data.value?.paymentAccounts ?? []);
    const defaultNotes = computed(() => data.value?.defaultNotes ?? null);

    /**
     * The rails that can settle `currency`, default first.
     *
     * Filtered client-side so switching the currency select from USD to EUR
     * re-narrows the picker instantly, with no round trip. An account with
     * `currency === null` settles anything, which is why it stays in every list.
     */
    function accountsForCurrency(
        currency: MaybeRefOrGetter<string>,
    ): InvoicePaymentAccountOption[] {
        const wanted = toValue(currency).toUpperCase();

        return paymentAccounts.value
            .filter(
                (account) =>
                    account.currency === null || account.currency === wanted,
            )
            .sort((a, b) => Number(b.is_default) - Number(a.is_default));
    }

    return {
        ...query,
        data,
        clients,
        services,
        products,
        paymentAccounts,
        defaultNotes,
        accountsForCurrency,
    };
}
