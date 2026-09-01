<script setup lang="ts">
import { computed } from 'vue';
import type { FilterSelectOption } from '@/common/form';
import { AppField, FilterSelect, FormDialog } from '@/common/form';
import { Input } from '@/components/ui/input';
import { useRoleForm } from '../composables/useRoleForm';
import type { PermissionName, Role, RoleDetail } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `role` is `null` in create mode; `useRoleForm` reads it at submit time to
 * decide between `POST` and `PUT`, so this component only carries the prop
 * through rather than branching on it itself.
 *
 * `availablePermissions` comes from the `RoleController::index` page prop
 * (`PermissionRepositoryPort::allActiveNames()`) rather than a second query:
 * the catalogue is already on the page, and the server validates every name
 * against the live table anyway.
 */
const {
    role = null,
    availablePermissions,
    protectedRoles = [],
} = defineProps<{
    role?: Role | RoleDetail | null;
    availablePermissions: readonly PermissionName[];
    /** System roles whose name is an invariant — see `SystemRoles::PROTECTED`. */
    protectedRoles?: readonly string[];
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useRoleForm({
    open,
    role: () => role,
    onSuccess: () => {
        open.value = false;
    },
});

const permissionOptions = computed<FilterSelectOption[]>(() =>
    availablePermissions.map((name) => ({ value: name, label: name })),
);

/**
 * A protected role's grants stay editable — only its *name* is an invariant, so
 * `UpdateRoleHandler` refuses a rename and accepts a permission sync. Locking
 * the field here states that rule up front instead of letting the user type a
 * new name and receive a rejection they cannot see (the module's flashes are
 * not surfaced to the client — see `roles/Index.vue`).
 */
const nameLocked = computed(
    () => role !== null && protectedRoles.includes(role.name),
);

/** reka's combobox models its value as `unknown`; narrow it back to names. */
function toPermissionNames(value: unknown): PermissionName[] {
    return Array.isArray(value) ? value.map((item) => String(item)) : [];
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="role ? 'Edit role' : 'New role'"
        description="A role is a named bundle of permissions. Changes are audit-logged and take effect immediately."
        submit-label="Save"
        content-class="sm:max-w-xl"
    >
        <div class="grid gap-4">
            <form.Field name="name" #default="{ field }">
                <AppField
                    :field="field"
                    label="Role name"
                    required
                    :description="
                        nameLocked
                            ? 'System roles cannot be renamed — their permissions can still be changed.'
                            : 'Letters, numbers, spaces, hyphens and underscores.'
                    "
                    #default="{ control }"
                >
                    <Input
                        v-bind="control"
                        :disabled="nameLocked"
                        :model-value="String(field.state.value ?? '')"
                        placeholder="CONTENT_EDITOR"
                        @blur="field.handleBlur"
                        @update:model-value="
                            (value) => field.handleChange(String(value))
                        "
                    />
                </AppField>
            </form.Field>

            <form.Field name="permissions" #default="{ field }">
                <AppField
                    :field="field"
                    label="Permissions"
                    description="The complete set this role should hold. Saving replaces the current grants — clearing it revokes them all."
                    #default="{ control }"
                >
                    <FilterSelect
                        v-bind="control"
                        multiple
                        class="w-full"
                        :options="permissionOptions"
                        placeholder="Select permissions…"
                        search-placeholder="Search permissions…"
                        empty-text="No permission matches that search."
                        :max-visible-chips="6"
                        :model-value="field.state.value"
                        @update:model-value="
                            (value) =>
                                field.handleChange(toPermissionNames(value))
                        "
                    />
                </AppField>
            </form.Field>
        </div>
    </FormDialog>
</template>
