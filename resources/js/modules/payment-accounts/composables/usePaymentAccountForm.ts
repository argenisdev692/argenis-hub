import type { Ref } from 'vue';
import { watch } from 'vue';
import { useAppForm } from '@/common/form';
import {
    emptyPaymentAccountFormValues,
    paymentAccountFormSchema,
    toPaymentAccountFormValues,
    toWritePayload,
} from '../schemas/paymentAccountFormSchema';
import type { PaymentAccount } from '../types';
import { usePaymentAccountMutations } from './usePaymentAccountMutations';

export type PaymentAccountFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed. `null` means
     * "create": the form starts empty and submits to `POST`. Anything else
     * means "edit": the form seeds from the record and submits to `PUT`.
     */
    account: () => PaymentAccount | null;
    onSuccess?: () => void;
};

/**
 * One form, two endpoints. Which one `handleSubmit` calls is decided at submit
 * time by whether `account()` is still non-null — the row being edited never
 * changes out from under an open dialog.
 */
export function usePaymentAccountForm({
    open,
    account,
    onSuccess,
}: PaymentAccountFormOptions) {
    const { createAccount, updateAccount } = usePaymentAccountMutations();

    const form = useAppForm({
        defaultValues: emptyPaymentAccountFormValues(),
        schema: paymentAccountFormSchema,
        onSubmit: async (values) => {
            const payload = toWritePayload(values);
            const current = account();

            try {
                if (current) {
                    await updateAccount.mutateAsync({
                        uuid: current.uuid,
                        payload,
                    });
                } else {
                    await createAccount.mutateAsync(payload);
                }
            } catch {
                // The mutation's own `onError` already toasted the failure;
                // swallow it here so the dialog stays open for another attempt.
                return;
            }

            onSuccess?.();
        },
    });

    watch(open, (isOpen) => {
        if (isOpen) {
            const current = account();

            form.reset(
                current
                    ? toPaymentAccountFormValues(current)
                    : emptyPaymentAccountFormValues(),
            );
        }
    });

    return form;
}
