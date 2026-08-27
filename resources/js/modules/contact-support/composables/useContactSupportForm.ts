import type { Ref } from 'vue';
import { watch } from 'vue';
import { useAppForm } from '@/common/form';
import {
    contactSupportFormSchema,
    emptyContactSupportFormValues,
    toContactSupportFormValues,
    toWritePayload,
} from '../schemas/contactSupportFormSchema';
import type { ContactSupport } from '../types';
import { useContactSupportMutations } from './useContactSupportMutations';

export type ContactSupportFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed. `null` means
     * "create": the form starts empty and submits to `POST`. Anything else
     * means "edit": the form seeds from the record and submits to `PUT`.
     */
    support: () => ContactSupport | null;
    onSuccess?: () => void;
};

/**
 * One form, two endpoints. Which one `handleSubmit` calls is decided at submit
 * time by whether `support()` is still non-null — the row being edited never
 * changes out from under an open dialog, so reading it once at that point is
 * equivalent to and simpler than threading a separate "mode" flag alongside it.
 */
export function useContactSupportForm({
    open,
    support,
    onSuccess,
}: ContactSupportFormOptions) {
    const { createContactSupport, updateContactSupport } =
        useContactSupportMutations();

    const form = useAppForm({
        defaultValues: emptyContactSupportFormValues(),
        schema: contactSupportFormSchema,
        onSubmit: async (values) => {
            const payload = toWritePayload(values);
            const current = support();

            try {
                if (current) {
                    await updateContactSupport.mutateAsync({
                        uuid: current.uuid,
                        payload,
                    });
                } else {
                    await createContactSupport.mutateAsync(payload);
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
            const current = support();

            form.reset(
                current
                    ? toContactSupportFormValues(current)
                    : emptyContactSupportFormValues(),
            );
        }
    });

    return form;
}
