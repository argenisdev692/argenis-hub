<script setup lang="ts">
import { useId } from 'vue';
import { FormDialog, TextField } from '@/common/form';
import { Checkbox } from '@/components/ui/checkbox';
import { useContactSupportForm } from '../composables/useContactSupportForm';
import type { ContactSupport } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `support` is `null` in create mode; `useContactSupportForm` reads it at submit
 * time to decide between `POST` and `PUT`, so this component only has to carry
 * the prop through, not branch on it itself.
 */
const { support = null } = defineProps<{
    support?: ContactSupport | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useContactSupportForm({
    open,
    support: () => support,
});

const smsConsentId = useId();
const readedId = useId();
const isSpamId = useId();
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="support ? 'Edit support request' : 'New support request'"
        description="Details captured from the public contact form. Edits are audit-logged."
        submit-label="Save"
    >
        <div class="grid gap-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="first_name" #default="{ field }">
                    <TextField
                        :field="field"
                        label="First name"
                        required
                        autocomplete="given-name"
                        placeholder="Ada"
                    />
                </form.Field>

                <form.Field name="last_name" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Last name"
                        required
                        autocomplete="family-name"
                        placeholder="Lovelace"
                    />
                </form.Field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="email" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Email"
                        type="email"
                        inputmode="email"
                        required
                        autocomplete="email"
                        placeholder="ada@example.com"
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

            <form.Field name="subject" #default="{ field }">
                <TextField
                    :field="field"
                    label="Subject"
                    required
                    placeholder="Question about pricing"
                />
            </form.Field>

            <form.Field name="message" #default="{ field }">
                <TextField
                    :field="field"
                    label="Message"
                    multiline
                    :rows="5"
                    required
                    placeholder="Write the request details here…"
                />
            </form.Field>

            <div class="grid gap-3 rounded-lg border border-border p-3">
                <form.Field name="readed" #default="{ field }">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :id="readedId"
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) => field.handleChange(value === true)
                            "
                        />
                        <label :for="readedId" class="text-sm font-medium">
                            Marked as read
                        </label>
                    </div>
                </form.Field>

                <form.Field name="is_spam" #default="{ field }">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :id="isSpamId"
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) => field.handleChange(value === true)
                            "
                        />
                        <label :for="isSpamId" class="text-sm font-medium">
                            Flagged as spam
                        </label>
                    </div>
                </form.Field>

                <form.Field name="sms_consent" #default="{ field }">
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :id="smsConsentId"
                            :model-value="field.state.value"
                            @update:model-value="
                                (value) => field.handleChange(value === true)
                            "
                        />
                        <label :for="smsConsentId" class="text-sm font-medium">
                            Consented to SMS follow-up
                        </label>
                    </div>
                </form.Field>
            </div>
        </div>
    </FormDialog>
</template>
