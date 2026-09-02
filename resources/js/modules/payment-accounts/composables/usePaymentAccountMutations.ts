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
} from '@/routes/payment-accounts/admin';
import type { PaymentAccount, PaymentAccountWritePayload } from '../types';

/** Every mutation below touches the same list, so one key invalidates all of it. */
const ACCOUNTS_KEY = ['payment-accounts'];

/** The invoice form offers these as the settlement picker's options. */
const INVOICE_FORM_OPTIONS_KEY = ['invoice-form-options'];

function errorMessage(error: unknown, fallback: string): string {
    return error instanceof HttpError ? error.message : fallback;
}

export function usePaymentAccountMutations() {
    const queryCache = useQueryCache();

    function invalidateAll(): void {
        queryCache.invalidateQueries({ key: ACCOUNTS_KEY });
        queryCache.invalidateQueries({ key: INVOICE_FORM_OPTIONS_KEY });
    }

    const createAccount = useMutation({
        mutation: (payload: PaymentAccountWritePayload) =>
            httpJson<PaymentAccount>(toUrl(store()), {
                method: 'POST',
                body: payload,
            }),
        onSuccess() {
            toast.success('Payment account created.');
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to create the account.'));
        },
    });

    const updateAccount = useMutation({
        mutation: ({
            uuid,
            payload,
        }: {
            uuid: string;
            payload: PaymentAccountWritePayload;
        }) =>
            httpJson<PaymentAccount>(toUrl(update(uuid)), {
                method: 'PUT',
                body: payload,
            }),
        onSuccess() {
            // Existing invoices keep their own snapshot — this only changes
            // which details a NEW invoice will copy.
            toast.success('Payment account updated.');
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to update the account.'));
        },
    });

    const deleteAccount = useMutation({
        mutation: (uuid: string) =>
            httpJson<void>(toUrl(destroy(uuid)), { method: 'DELETE' }),
        onSuccess() {
            toast.success('Payment account deleted.');
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to delete the account.'));
        },
    });

    const restoreAccount = useMutation({
        mutation: (uuid: string) =>
            httpJson<PaymentAccount>(toUrl(restore(uuid)), { method: 'PATCH' }),
        onSuccess() {
            toast.success('Payment account restored.');
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(errorMessage(error, 'Failed to restore the account.'));
        },
    });

    const bulkDeleteAccounts = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ deleted: number }>(toUrl(bulkDelete()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ deleted }) {
            toast.success(
                `${deleted} ${deleted === 1 ? 'account' : 'accounts'} deleted.`,
            );
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to delete the selected accounts.'),
            );
        },
    });

    const bulkRestoreAccounts = useMutation({
        mutation: (uuids: string[]) =>
            httpJson<{ restored: number }>(toUrl(bulkRestore()), {
                method: 'POST',
                body: { uuids },
            }),
        onSuccess({ restored }) {
            toast.success(
                `${restored} ${restored === 1 ? 'account' : 'accounts'} restored.`,
            );
            invalidateAll();
        },
        onError(error: unknown) {
            toast.error(
                errorMessage(error, 'Failed to restore the selected accounts.'),
            );
        },
    });

    return {
        createAccount,
        updateAccount,
        deleteAccount,
        restoreAccount,
        bulkDeleteAccounts,
        bulkRestoreAccounts,
    };
}
