<script setup lang="ts">
import { computed } from 'vue';
import type { FilterSelectOption } from '@/common/form';
import {
    AppField,
    FileDropzone,
    FilterSelect,
    FormDialog,
    TextField,
} from '@/common/form';
import { Switch } from '@/components/ui/switch';
import { useCvForm } from '../composables/useCvForm';
import {
    CV_NICHES,
    cvFileTypePresentation,
    cvNichePresentation,
} from '../helpers/cvPresentation';
import { ACCEPTED_CV_TYPES, MAX_CV_MB } from '../schemas/cvFormSchema';
import type { Cv, CvNiche } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `cv` is `null` in create mode; `useCvForm` reads it at submit time to decide
 * between `store` and `update`, and at validation time to decide whether the
 * file is required, so this component only has to carry the prop through rather
 * than branch on it itself.
 */
const { cv = null } = defineProps<{
    cv?: Cv | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useCvForm({ open, cv: () => cv });

const nicheOptions = computed<FilterSelectOption[]>(() =>
    CV_NICHES.map((niche) => ({
        value: niche,
        label: cvNichePresentation(niche).label,
    })),
);

/** reka's combobox models its value as `unknown`; narrow it back to a niche. */
function toNiche(value: unknown): CvNiche {
    return value === 'other' ? 'other' : 'fullstack';
}

const storedFile = computed(() =>
    cv
        ? `${cv.original_filename} · ${cvFileTypePresentation(cv.file_type).label}`
        : null,
);
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="cv ? 'Edit CV' : 'Upload CV'"
        description="Résumés are stored privately and only ever shared through short-lived signed links."
        :submit-label="cv ? 'Save' : 'Upload'"
        content-class="sm:max-w-xl"
    >
        <div class="grid gap-4">
            <form.Field name="title" #default="{ field }">
                <TextField
                    :field="field"
                    label="Title"
                    required
                    placeholder="Senior Full-stack Engineer — 2026"
                    description="How this CV is listed. Only you can see it."
                />
            </form.Field>

            <form.Field name="niche" #default="{ field }">
                <AppField
                    :field="field"
                    label="Niche"
                    required
                    description="Full-stack is the developer track; Other covers everything the RAG pipeline treats generically."
                    #default="{ control }"
                >
                    <FilterSelect
                        v-bind="control"
                        class="w-full"
                        :options="nicheOptions"
                        :clearable="false"
                        placeholder="Select a niche…"
                        :model-value="field.state.value"
                        @update:model-value="
                            (value) => field.handleChange(toNiche(value))
                        "
                    />
                </AppField>
            </form.Field>

            <form.Field name="is_primary" #default="{ field }">
                <AppField
                    :field="field"
                    label="Primary CV"
                    orientation="horizontal"
                    description="The one used by default. Turning this on clears the flag on every other CV you own."
                    #default="{ control }"
                >
                    <Switch
                        v-bind="control"
                        :model-value="field.state.value"
                        @update:model-value="
                            (value) => field.handleChange(Boolean(value))
                        "
                    />
                </AppField>
            </form.Field>

            <!--
                The stored filename sits above the dropzone rather than inside
                it: in edit mode the field means "replace this", and naming what
                is about to be replaced is the difference between a confident
                upload and a cancelled one. Hidden in create mode, where there
                is nothing to replace.
            -->
            <p
                v-if="storedFile"
                class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
            >
                Currently stored: <strong>{{ storedFile }}</strong
                >. Leave the field below empty to keep it.
            </p>

            <form.Field name="file" #default="{ field }">
                <AppField
                    :field="field"
                    label="CV file"
                    :required="!cv"
                    :description="`PDF or Markdown, up to ${MAX_CV_MB} MB.${cv ? ' Optional — only upload to replace the stored file.' : ''}`"
                    #default="{ control }"
                >
                    <FileDropzone
                        v-bind="control"
                        :model-value="field.state.value"
                        :accept="[...ACCEPTED_CV_TYPES]"
                        :max-size-mb="MAX_CV_MB"
                        hint="PDF or Markdown (.md)"
                        @update:model-value="field.handleChange($event)"
                    />
                </AppField>
            </form.Field>
        </div>
    </FormDialog>
</template>
