import type { Ref } from 'vue';
import { watch } from 'vue';
import { useAppForm } from '@/common/form';
import {
    emptyServiceFormValues,
    serviceFormSchema,
    toServiceFormValues,
    toWritePayload,
} from '../schemas/serviceFormSchema';
import type { Service } from '../types';
import { useServiceMutations } from './useServiceMutations';

export type ServiceFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed. `null` means
     * "create": the form starts empty and submits to `POST`. Anything else
     * means "edit": the form seeds from the record and submits to `PUT`.
     */
    service: () => Service | null;
    onSuccess?: () => void;
};

/**
 * One form, two endpoints. Which one `handleSubmit` calls is decided at
 * submit time by whether `service()` is still non-null — the row being
 * edited never changes out from under an open dialog, so reading it once at
 * that point is equivalent to and simpler than threading a separate "mode"
 * flag alongside it.
 */
export function useServiceForm({
    open,
    service,
    onSuccess,
}: ServiceFormOptions) {
    const { createService, updateService } = useServiceMutations();

    const form = useAppForm({
        defaultValues: emptyServiceFormValues(),
        schema: serviceFormSchema,
        onSubmit: async (values) => {
            const payload = toWritePayload(values);
            const current = service();

            try {
                if (current) {
                    await updateService.mutateAsync({
                        uuid: current.uuid,
                        payload,
                    });
                } else {
                    await createService.mutateAsync(payload);
                }
            } catch {
                // The mutation's own `onError` already toasted the failure;
                // swallow it here so it never reaches `handleSubmit`'s
                // caller (`FormDialog` awaits it without a try/catch) and
                // leaves the dialog open for another attempt.
                return;
            }

            onSuccess?.();
        },
    });

    watch(open, (isOpen) => {
        if (isOpen) {
            const current = service();

            form.reset(
                current
                    ? toServiceFormValues(current)
                    : emptyServiceFormValues(),
            );
        }
    });

    return form;
}
