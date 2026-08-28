<script setup lang="ts">
import { AppField, FormDialog, TextField } from '@/common/form';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useClientForm } from '../composables/useClientForm';
import { clientStatusLabel } from '../helpers/clientPresentation';
import {
    CLIENT_STATUS_VALUES,
    isClientStatus,
} from '../schemas/clientFormSchema';
import type { Client } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `client` is `null` in create mode; `useClientForm` reads it at submit time to
 * decide between `POST` and `PUT`, so this component only has to carry the prop
 * through, not branch on it itself.
 */
const { client = null } = defineProps<{
    client?: Client | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useClientForm({
    open,
    client: () => client,
});
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="client ? 'Edit client' : 'New client'"
        description="CRM record for a company or person you work with. Edits are audit-logged."
        submit-label="Save"
        content-class="sm:max-w-2xl"
    >
        <div class="grid gap-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="client_name" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Client name"
                        required
                        autocomplete="organization"
                        placeholder="Acme Inc."
                    />
                </form.Field>

                <form.Field name="status" #default="{ field }">
                    <AppField
                        :field="field"
                        label="Lifecycle status"
                        required
                        #default="{ control }"
                    >
                        <Select
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) =>
                                    isClientStatus(value) &&
                                    field.handleChange(value)
                            "
                        >
                            <SelectTrigger v-bind="control" class="w-full">
                                <SelectValue placeholder="Select a status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in CLIENT_STATUS_VALUES"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ clientStatusLabel(status) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </AppField>
                </form.Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="email" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Email"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        placeholder="hello@acme.com"
                    />
                </form.Field>

                <form.Field name="phone" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Phone"
                        type="tel"
                        inputmode="tel"
                        required
                        autocomplete="tel"
                        placeholder="+15551234567"
                        description="7–15 digits, optionally starting with “+”."
                    />
                </form.Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                <form.Field name="country" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Country"
                        autocomplete="country-name"
                        placeholder="United States"
                    />
                </form.Field>

                <form.Field name="country_code" #default="{ field }">
                    <TextField
                        :field="field"
                        label="ISO code"
                        placeholder="US"
                        description="2 letters."
                    />
                </form.Field>
            </div>

            <form.Field name="address" #default="{ field }">
                <TextField
                    :field="field"
                    label="Address"
                    autocomplete="street-address"
                    placeholder="123 Market St, Suite 400"
                />
            </form.Field>

            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="tax_id" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Tax ID"
                        placeholder="12-3456789"
                    />
                </form.Field>

                <form.Field name="nif" #default="{ field }">
                    <TextField
                        :field="field"
                        label="NIF"
                        placeholder="B12345678"
                    />
                </form.Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="website" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Website"
                        type="url"
                        inputmode="url"
                        placeholder="https://acme.com"
                    />
                </form.Field>

                <form.Field name="linkedin_link" #default="{ field }">
                    <TextField
                        :field="field"
                        label="LinkedIn"
                        type="url"
                        inputmode="url"
                        placeholder="https://linkedin.com/company/acme"
                    />
                </form.Field>

                <form.Field name="facebook_link" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Facebook"
                        type="url"
                        inputmode="url"
                        placeholder="https://facebook.com/acme"
                    />
                </form.Field>

                <form.Field name="instagram_link" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Instagram"
                        type="url"
                        inputmode="url"
                        placeholder="https://instagram.com/acme"
                    />
                </form.Field>

                <form.Field name="twitter_link" #default="{ field }">
                    <TextField
                        :field="field"
                        label="X / Twitter"
                        type="url"
                        inputmode="url"
                        placeholder="https://x.com/acme"
                    />
                </form.Field>
            </div>

            <form.Field name="notes" #default="{ field }">
                <TextField
                    :field="field"
                    label="Notes"
                    multiline
                    :rows="4"
                    placeholder="Context worth keeping on this relationship…"
                />
            </form.Field>
        </div>
    </FormDialog>
</template>
