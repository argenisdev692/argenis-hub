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

/** Every mutation below touches the same list, so one key invalidates all of it. */
const INVOICES_KEY = ['invoices'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export function useInvoiceMutations() {
    const queryCache = useQueryCache();

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
        onSuccess() {
            toast.success('Invoice updated.');
            queryCache.invalidateQueries({ key: INVOICES_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the invoice.'));
        },
    });

    const deleteInvoice = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Invoice deleted.');
            queryCache.invalidateQueries({ key: INVOICES_KEY });
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to delete the invoice.'));
        },
    });

    const restoreInvoice = useMutation({
        mutation: (uuid: string) =>
            httpJson<InvoiceDetail>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess() {
            toast.success('Invoice restored.');
            queryCache.invalidateQueries({ key: INVOICES_KEY });
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
        onSuccess({ deleted }) {
            toast.success(
                `${deleted} ${deleted === 1 ? 'invoice' : 'invoices'} deleted.`,
            );
            queryCache.invalidateQueries({ key: INVOICES_KEY });
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
        onSuccess({ restored }) {
            toast.success(
                `${restored} ${restored === 1 ? 'invoice' : 'invoices'} restored.`,
            );
            queryCache.invalidateQueries({ key: INVOICES_KEY });
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
