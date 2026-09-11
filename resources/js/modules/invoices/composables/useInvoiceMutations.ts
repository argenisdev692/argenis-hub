import { useMutation, useQueryCache } from '@pinia/colada';
import { toast } from 'vue-sonner';
import { httpJson, HttpError } from '@/lib/http';
import { toUrl } from '@/lib/utils';
import {
    bulkDelete,
    bulkRestore,
    destroy,
    restore,
    store,
    update,
} from '@/routes/invoices/admin';
import type { InvoiceDetail, InvoiceWritePayload } from '../types';
// Every mutation below touches the same list, so one key invalidates all of it.
// Imported rather than redeclared: two copies of a cache key drift silently —
// the writes keep succeeding and the table simply stops refreshing.
import { invoiceKey } from './useInvoice';
import { INVOICES_KEY } from './useInvoices';

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export function useInvoiceMutations() {
    const queryCache = useQueryCache();

    /** Drops the cached detail of every row a bulk action just touched. */
    function invalidateDetails(uuids: readonly string[]): void {
        for (const uuid of uuids) {
            queryCache.invalidateQueries({ key: invoiceKey(uuid) });
        }
    }

    const createInvoice = useMutation({
        mutation: (payload: InvoiceWritePayload) =>
            httpJson<InvoiceDetail>(toUrl(store()), {
                method: 'POST',
                body: payload,
            }),
        onSuccess() {
            toast.success('Invoice created.');
            queryCache.invalidateQueries({ key: INVOICES_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to create the invoice.'));
        },
    });

    const updateInvoice = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: InvoiceWritePayload;
        }) =>
            httpJson<InvoiceDetail>(toUrl(update(uuid)), {
                method: 'PUT',
                body: payload,
            }),
        onSuccess(_data, { uuid }) {
            toast.success('Invoice updated.');
            queryCache.invalidateQueries({ key: INVOICES_KEY });
            // The detail record is cached under its own key and outlives the
            // list by design (`useInvoice`), so invalidating the list alone
            // would leave the dialog showing the pre-edit line items.
            queryCache.invalidateQueries({ key: invoiceKey(uuid) });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the invoice.'));
        },
    });

    const deleteInvoice = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess(_data, uuid) {
            toast.success('Invoice deleted.');
            queryCache.invalidateQueries({ key: INVOICES_KEY });
            queryCache.invalidateQueries({ key: invoiceKey(uuid) });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to delete the invoice.'));
        },
    });

    const restoreInvoice = useMutation({
        mutation: (uuid: string) =>
            httpJson<InvoiceDetail>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess(_data, uuid) {
            toast.success('Invoice restored.');
            queryCache.invalidateQueries({ key: INVOICES_KEY });
            queryCache.invalidateQueries({ key: invoiceKey(uuid) });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the invoice.'));
        },
    });

    const bulkDeleteInvoices = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ deleted: number }>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ deleted }, uuids) {
            toast.success(
                `${deleted} ${deleted === 1 ? 'invoice' : 'invoices'} deleted.`,
            );
            queryCache.invalidateQueries({ key: INVOICES_KEY });
            invalidateDetails(uuids);
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to delete the selected invoices.'),
            );
        },
    });

    const bulkRestoreInvoices = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ restored: number }>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ restored }, uuids) {
            toast.success(
                `${restored} ${restored === 1 ? 'invoice' : 'invoices'} restored.`,
            );
            queryCache.invalidateQueries({ key: INVOICES_KEY });
            invalidateDetails(uuids);
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected invoices.'),
            );
        },
    });

    return {
        createInvoice,
        updateInvoice,
        deleteInvoice,
        restoreInvoice,
        bulkDeleteInvoices,
        bulkRestoreInvoices,
    };
}
