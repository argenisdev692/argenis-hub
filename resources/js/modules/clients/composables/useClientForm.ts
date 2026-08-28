import type { Ref } from 'vue';
import { watch } from 'vue';
import { useAppForm } from '@/common/form';
import {
    clientFormSchema,
    emptyClientFormValues,
    toClientFormValues,
    toWritePayload,
} from '../schemas/clientFormSchema';
import type { Client } from '../types';
import { useClientMutations } from './useClientMutations';

export type ClientFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed. `null` means
     * "create": the form starts empty and submits to `POST`. Anything else
     * means "edit": the form seeds from the record and submits to `PUT`.
     */
    client: () => Client | null;
    onSuccess?: () => void;
};

/**
 * One form, two endpoints. Which one `handleSubmit` calls is decided at submit
 * time by whether `client()` is still non-null — the row being edited never
 * changes out from under an open dialog, so reading it once at that point is
 * equivalent to and simpler than threading a separate "mode" flag alongside it.
 */
export function useClientForm({ open, client, onSuccess }: ClientFormOptions) {
    const { createClient, updateClient } = useClientMutations();

    const form = useAppForm({
        defaultValues: emptyClientFormValues(),
        schema: clientFormSchema,
        onSubmit: async (values) => {
            const payload = toWritePayload(values);
            const current = client();

            try {
                if (current) {
                    await updateClient.mutateAsync({
                        uuid: current.uuid,
                        payload,
                    });
                } else {
                    await createClient.mutateAsync(payload);
                }
            } catch {
                // The mutation's own `onError` already toasted the failure;
                // swallow it here so it never reaches `handleSubmit`'s caller
                // (`FormDialog` awaits it without a try/catch) and leaves the
                // dialog open for another attempt.
                return;
            }

            onSuccess?.();
        },
    });

    watch(open, (isOpen) => {
        if (isOpen) {
            const current = client();

            form.reset(
                current ? toClientFormValues(current) : emptyClientFormValues(),
            );
        }
    });

    return form;
}
