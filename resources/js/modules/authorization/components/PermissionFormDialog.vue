<script setup lang="ts">
import { FormDialog, TextField } from '@/common/form';
import { usePermissionForm } from '../composables/usePermissionForm';
import type { Permission, PermissionDetail } from '../types';

/**
 * One dialog for both create and edit. `permission` is `null` in create mode;
 * `usePermissionForm` reads it at submit time to pick `POST` or `PUT`.
 */
const { permission = null } = defineProps<{
    permission?: Permission | PermissionDetail | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = usePermissionForm({
    open,
    permission: () => permission,
    onSuccess: () => {
        open.value = false;
    },
});
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="permission ? 'Edit permission' : 'New permission'"
        description="Permissions are the atoms roles are built from. Renaming one takes effect wherever it is granted."
        submit-label="Save"
        content-class="sm:max-w-lg"
    >
        <form.Field name="name" #default="{ field }">
            <TextField
                :field="field"
                label="Permission name"
                required
                placeholder="VIEW_ANY_INVOICES"
                description="Upper snake case, {ACTION}_{MODULE} — e.g. “VIEW_ANY_ROLES”."
            />
        </form.Field>
    </FormDialog>
</template>
