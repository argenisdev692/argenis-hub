import type { Ref } from 'vue';
import { watch } from 'vue';
import { useAppForm } from '@/common/form';
import {
    emptyPermissionFormValues,
    permissionFormSchema,
    toPermissionFormValues,
    toPermissionWritePayload,
} from '../schemas/permissionFormSchema';
import type { Permission, PermissionDetail } from '../types';
import { usePermissionMutations } from './usePermissionMutations';

export type PermissionFormOptions = {
    /** The dialog's `open` model. The form re-seeds each time it opens. */
    open: Ref<boolean>;
    /** A getter, read fresh on every re-seed. `null` means create. */
    permission: () => Permission | PermissionDetail | null;
    onSuccess?: () => void;
};

/** One form, two endpoints — same shape as {@see useRoleForm}. */
export function usePermissionForm({
    open,
    permission,
    onSuccess,
}: PermissionFormOptions) {
    const { createPermission, updatePermission } = usePermissionMutations();

    const form = useAppForm({
        defaultValues: emptyPermissionFormValues(),
        schema: permissionFormSchema,
        onSubmit: async (values) => {
            const payload = toPermissionWritePayload(values);
            const current = permission();

            try {
                if (current) {
                    await updatePermission.mutateAsync({
                        uuid: current.uuid,
                        payload,
                    });
                } else {
                    await createPermission.mutateAsync(payload);
                }
            } catch {
                // Already toasted by the mutation's `onError` — see useRoleForm.
                return;
            }

            onSuccess?.();
        },
    });

    watch(open, (isOpen) => {
        if (isOpen) {
            const current = permission();

            form.reset(
                current
                    ? toPermissionFormValues(current)
                    : emptyPermissionFormValues(),
            );
        }
    });

    return form;
}
