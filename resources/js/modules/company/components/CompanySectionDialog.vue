<script setup lang="ts">
import { FormDialog, TextField } from '@/common/form';
import { useCompanySectionForm } from '../composables/useCompanyForm';
import type { CompanyFieldSpec } from '../helpers/companyFields';
import { toControlProps } from '../helpers/companyFields';
import type { CompanyProfile } from '../types';

/**
 * One dialog, driven by a field list.
 *
 * Identity, contact, address, fiscal and socials differ only in their heading
 * and which fields they render — the form, the schema, the payload and the
 * endpoint are identical for all five, because the backend takes the whole
 * record on every write. Five near-identical components would mean five places
 * to forget the discard guard or the re-seed on open.
 *
 * Brand marks are the exception and get their own dialog: they post multipart
 * to a different route with different validation.
 */
const {
    company,
    title,
    description,
    fields,
    successMessage = 'Company details updated.',
} = defineProps<{
    company: CompanyProfile;
    title: string;
    description?: string;
    fields: readonly CompanyFieldSpec[];
    successMessage?: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useCompanySectionForm({
    // A getter: `company` is a destructured prop, so reading it eagerly would
    // pin the form to the record as it was on first render.
    company: () => company,
    open,
    successMessage,
});
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="title"
        :description="description"
        submit-label="Save changes"
        content-class="sm:max-w-2xl"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <form.Field
                v-for="spec in fields"
                :key="spec.name"
                :name="spec.name"
                #default="{ field }"
            >
                <div :class="spec.wide ? 'sm:col-span-2' : undefined">
                    <TextField :field="field" v-bind="toControlProps(spec)" />
                </div>
            </form.Field>
        </div>
    </FormDialog>
</template>
