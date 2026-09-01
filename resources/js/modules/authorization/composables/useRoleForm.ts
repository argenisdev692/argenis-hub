import type { Ref } from 'vue';
import { watch } from 'vue';
import { useAppForm } from '@/common/form';
import {
    emptyRoleFormValues,
    roleFormSchema,
    toRoleFormValues,
    toRoleWritePayload,
} from '../schemas/roleFormSchema';
import type { Role, RoleDetail } from '../types';
import { useRoleMutations } from './useRoleMutations';

export type RoleFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /**
     * A getter, not the value — read fresh on every re-seed. `null` means
     * "create": the form starts empty and submits to `POST`. Anything else
     * means "edit": the form seeds from the record and submits to `PUT`.
     */
    role: () => Role | RoleDetail | null;
    onSuccess?: () => void;
};

/**
 * One form, two endpoints. Which one `handleSubmit` calls is decided at submit
 * time by whether `role()` is still non-null — the row being edited never
 * changes out from under an open dialog, so reading it once at that point is
 * equivalent to and simpler than threading a separate "mode" flag alongside it.
 */
export function useRoleForm({ open, role, onSuccess }: RoleFormOptions) {
    const { createRole, updateRole } = useRoleMutations();

    const form = useAppForm({
        defaultValues: emptyRoleFormValues(),
        schema: roleFormSchema,
        onSubmit: async (values) => {
            const payload = toRoleWritePayload(values);
            const current = role();

            try {
                if (current) {
                    await updateRole.mutateAsync({
                        uuid: current.uuid,
                        payload,
                    });
                } else {
                    await createRole.mutateAsync(payload);
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
            const current = role();

            form.reset(
                current ? toRoleFormValues(current) : emptyRoleFormValues(),
            );
        }
    });

    return form;
}
