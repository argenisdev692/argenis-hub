import { useQuery } from '@pinia/colada';
import type { MaybeRefOrGetter } from 'vue';
import { computed, toValue } from 'vue';
import { httpJson } from '@/lib/http';
import { checkNumber, nextNumber } from '@/routes/invoices/admin';
import type { InvoiceNumberCheck, NextInvoiceNumber } from '../types';

/**
 * The sequence the next invoice of `year` should take.
 *
 * Asked of the server rather than derived from the list: the number is a
 * per-year sequence with a uniqueness constraint behind it, and the highest
 * number on page one is not the highest number in the table once a filter is
 * applied. `SuggestNextInvoiceNumberHandler` reads `nextSequenceForYear()`
 * directly, which is the only place that can answer it correctly.
 *
 * Never cached for long: two operators drafting invoices at the same time both
 * want the *current* answer, and the loser of that race is told by
 * `useInvoiceNumberCheck` before they can submit.
 */
export function useNextInvoiceNumber(
    year: MaybeRefOrGetter<number | null>,
    enabled: MaybeRefOrGetter<boolean>,
) {
    const { data, ...query } = useQuery<NextInvoiceNumber>({
        key: () => ['invoice-next-number', toValue(year) ?? 'current'],
        query: () => {
            const requested = toValue(year);

            return httpJson<NextInvoiceNumber>(
                nextNumber.url(
                    requested === null ? {} : { query: { year: requested } },
                ),
            );
        },
        enabled: () => toValue(enabled),
        staleTime: 0,
        gcTime: 1000 * 30,
    });

    return { ...query, data };
}

/**
 * Whether `invoice_number` is still free, checked as the operator types.
 *
 * A UX affordance, not the constraint: the unique index on
 * `(year, sequence)` is what actually prevents a duplicate, and this only
 * surfaces the collision early enough that they can fix it before filling in
 * twenty line items. `ignore` carries the uuid being edited so an invoice never
 * reports a conflict with itself.
 *
 * `GET .../check-number` is `throttle:30,1`, so the query is keyed on the
 * normalised number and left stale for a minute: re-typing a number that was
 * already checked is served from cache instead of spending another request
 * against that budget. The caller debounces the keystrokes on top of this.
 */
export function useInvoiceNumberCheck(
    invoiceNumber: MaybeRefOrGetter<string>,
    ignoreUuid: MaybeRefOrGetter<string | null>,
) {
    /** `014/2026`, or a bare `14` the server will expand — anything else is a no-op. */
    const isCheckable = computed(() =>
        /^(\d{1,6}|\d{1,6}\/\d{4})$/.test(toValue(invoiceNumber).trim()),
    );

    const { data, ...query } = useQuery<InvoiceNumberCheck>({
        key: () => [
            'invoice-number-check',
            toValue(invoiceNumber).trim(),
            toValue(ignoreUuid) ?? '',
        ],
        query: () => {
            const ignore = toValue(ignoreUuid);

            return httpJson<InvoiceNumberCheck>(
                checkNumber.url({
                    query: {
                        invoice_number: toValue(invoiceNumber).trim(),
                        ...(ignore === null ? {} : { ignore }),
                    },
                }),
            );
        },
        enabled: () => isCheckable.value,
        staleTime: 1000 * 60,
        gcTime: 1000 * 60 * 5,
    });

    /**
     * `null` while the answer is unknown — not yet checkable, or in flight.
     *
     * Three states rather than a boolean, so the field can stay neutral instead
     * of flashing "taken" for the moment before the first response lands.
     */
    const isAvailable = computed<boolean | null>(() =>
        isCheckable.value && data.value ? data.value.available : null,
    );

    const conflict = computed(() => data.value?.invoice ?? null);

    return { ...query, data, isCheckable, isAvailable, conflict };
}
