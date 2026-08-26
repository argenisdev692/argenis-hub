<script setup lang="ts">
import { watch } from 'vue';
import { AppField, FileDropzone, FormDialog, useAppForm } from '@/common/form';
import { update as updateLogos } from '@/routes/company/logos';
import {
    ACCEPTED_LOGO_TYPES,
    companyLogosSchema,
    EMPTY_LOGOS_FORM,
    MAX_LOGO_MB,
    toLogosPayload,
} from '../schemas/companyLogosSchema';
import type { LogoVariant } from '../types';

/**
 * Brand-mark upload — the one write that does not go through
 * `CompanySectionDialog`.
 *
 * It posts multipart to its own route, validates files rather than strings, and
 * is rate-limited harder server-side because every accepted image is decoded
 * and re-encoded. None of that composes with the text-field dialog, so it stays
 * separate instead of growing a `mode` prop.
 *
 * All three marks are optional and at least one is required — the operator
 * replaces one logo without re-uploading the other two.
 */
const open = defineModel<boolean>('open', { default: false });

type LogoUploadSpec = {
    name: LogoVariant;
    label: string;
    description: string;
};

const UPLOADS: readonly LogoUploadSpec[] = [
    {
        name: 'logo',
        label: 'Primary logo',
        description: 'Shown on light backgrounds.',
    },
    {
        name: 'logo_white',
        label: 'Reversed logo',
        description: 'Shown on dark backgrounds.',
    },
    {
        name: 'mark',
        label: 'Mark',
        description: 'Square glyph used for favicons and avatars.',
    },
];

const form = useAppForm({
    defaultValues: { ...EMPTY_LOGOS_FORM },
    schema: companyLogosSchema,
    submit: {
        target: updateLogos(),
        transform: toLogosPayload,
        // Files cannot ride a JSON body; Inertia needs the multipart path.
        forceFormData: true,
        successMessage: 'Brand marks updated.',
        onSuccess: () => {
            open.value = false;
        },
    },
});

/**
 * Cleared on open rather than on close.
 *
 * A failed upload leaves the chosen files in place so the operator can read the
 * error and retry without re-picking them; wiping on close would also wipe them
 * when the discard guard bounced the user back into the dialog.
 */
watch(open, (isOpen) => {
    if (isOpen) {
        form.reset({ ...EMPTY_LOGOS_FORM });
    }
});
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        title="Replace brand marks"
        description="Upload only the marks you want to change. PNG, JPEG or WebP."
        submit-label="Upload"
        content-class="sm:max-w-xl"
    >
        <div class="flex flex-col gap-4">
            <form.Field
                v-for="upload in UPLOADS"
                :key="upload.name"
                :name="upload.name"
                #default="{ field }"
            >
                <AppField
                    :field="field"
                    :label="upload.label"
                    :description="upload.description"
                    #default="{ control }"
                >
                    <FileDropzone
                        v-bind="control"
                        :model-value="field.state.value"
                        :accept="[...ACCEPTED_LOGO_TYPES]"
                        :max-size-mb="MAX_LOGO_MB"
                        @update:model-value="field.handleChange($event)"
                    />
                </AppField>
            </form.Field>
        </div>
    </FormDialog>
</template>
