<script setup lang="ts">
import { useId } from 'vue';
import { FormDialog, TextField } from '@/common/form';
import { Checkbox } from '@/components/ui/checkbox';
import { useServiceForm } from '../composables/useServiceForm';
import type { Service } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `service` is `null` in create mode; `useServiceForm` reads it at submit
 * time to decide between `POST` and `PUT`, so this component only has to
 * carry the prop through, not branch on it itself.
 */
const { service = null } = defineProps<{
    service?: Service | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useServiceForm({
    open,
    service: () => service,
});

const activeFieldId = useId();
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="service ? 'Edit service' : 'New service'"
        description="Shown in the public booking form's service select when active."
        submit-label="Save"
    >
        <div class="grid gap-4">
            <form.Field name="name" #default="{ field }">
                <TextField
                    :field="field"
                    label="Name"
                    required
                    placeholder="Landing Page"
                />
            </form.Field>

            <form.Field name="slug" #default="{ field }">
                <TextField
                    :field="field"
                    label="Slug"
                    required
                    placeholder="landing_page"
                    description="Lowercase letters, digits and underscores only."
                />
            </form.Field>

            <form.Field name="description" #default="{ field }">
                <TextField
                    :field="field"
                    label="Description"
                    multiline
                    :rows="3"
                    placeholder="Single-page site optimized for one conversion goal."
                />
            </form.Field>

            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="sort_order" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Sort order"
                        type="number"
                        inputmode="numeric"
                        description="Lower numbers list first."
                    />
                </form.Field>

                <form.Field name="is_active" #default="{ field }">
                    <div class="flex items-center gap-2 pt-6">
                        <Checkbox
                            :id="activeFieldId"
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) => field.handleChange(value === true)
                            "
                        />
                        <label :for="activeFieldId" class="text-sm font-medium">
                            Active
                        </label>
                    </div>
                </form.Field>
            </div>
        </div>
    </FormDialog>
</template>
