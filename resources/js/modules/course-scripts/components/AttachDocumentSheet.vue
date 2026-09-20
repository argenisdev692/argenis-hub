<script setup lang="ts">
import { computed } from 'vue';
import { AppField, FileDropzone, FormSheet } from '@/common/form';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { useCourseContentMutations } from '../composables/useCourseContentMutations';
import { useCourseEditForm } from '../composables/useCourseEditForm';
import { documentKindLabel } from '../helpers/coursePresentation';
import type {
    AttachableKind,
    AttachDocumentFormValues,
} from '../schemas/courseEditSchemas';
import {
    ATTACHABLE_KINDS,
    attachDocumentSchema,
    emptyAttachDocumentValues,
    toAttachDocumentFormData,
} from '../schemas/courseEditSchemas';
import { acceptedTypes, maxSizeMb } from '../schemas/courseFormSchema';
import type { CourseUploadLimits } from '../types';

/**
 * Adds one source document after upload: content material (optionally tied
 * to a video number) or a course-wide style reference. Same file rules as the
 * create form — both read `limits` from the same config.
 */
const { courseUuid, limits } = defineProps<{
    courseUuid: string;
    limits: CourseUploadLimits;
}>();

const open = defineModel<boolean>('open', { default: false });

const { attachDocument } = useCourseContentMutations(() => courseUuid);

const form = useCourseEditForm<AttachDocumentFormValues>({
    open,
    schema: attachDocumentSchema(limits),
    seed: emptyAttachDocumentValues,
    submit: (values) =>
        attachDocument.mutateAsync({
            kind: values.kind,
            body: toAttachDocumentFormData(values),
        }),
});

const kind = form.useStore((state) => state.values.kind);
const isSubmitting = form.useStore((state) => state.isSubmitting);

const accept = computed(() => acceptedTypes(limits));
const sizeMb = computed(() => maxSizeMb(limits));

function isAttachableKind(value: unknown): value is AttachableKind {
    return (
        typeof value === 'string' &&
        (ATTACHABLE_KINDS as readonly string[]).includes(value)
    );
}

/** An empty number input means "not tied to a specific video". */
function toVideoNumber(value: string | number): number | null {
    return value === '' ? null : Number(value);
}
</script>

<template>
    <FormSheet
        v-model:open="open"
        :form="form"
        title="Attach a document"
        description="Content grounds the scripts in your material; a style reference shows the voice to imitate."
        submit-label="Attach"
    >
        <div class="grid gap-4">
            <form.Field name="kind" #default="{ field }">
                <AppField :field="field" label="Type" #default="{ control }">
                    <NativeSelect
                        v-bind="control"
                        :model-value="field.state.value"
                        @update:model-value="
                            isAttachableKind($event) &&
                            field.handleChange($event)
                        "
                    >
                        <NativeSelectOption
                            v-for="option in ATTACHABLE_KINDS"
                            :key="option"
                            :value="option"
                        >
                            {{ documentKindLabel(option) }}
                        </NativeSelectOption>
                    </NativeSelect>
                </AppField>
            </form.Field>

            <form.Field name="files" #default="{ field }">
                <AppField
                    :field="field"
                    label="File"
                    required
                    :description="`Up to ${sizeMb} MB.`"
                    #default="{ control }"
                >
                    <FileDropzone
                        v-bind="control"
                        :model-value="field.state.value"
                        :accept="accept"
                        :max-size-mb="sizeMb"
                        :disabled="isSubmitting"
                        @update:model-value="field.handleChange($event)"
                    />
                </AppField>
            </form.Field>

            <form.Field
                v-if="kind === 'content'"
                name="video_number"
                #default="{ field }"
            >
                <AppField
                    :field="field"
                    label="Video #"
                    description="Leave empty to use it for the whole course."
                    #default="{ control }"
                >
                    <Input
                        v-bind="control"
                        type="number"
                        min="1"
                        step="1"
                        placeholder="Any"
                        class="sm:w-32"
                        :model-value="field.state.value ?? ''"
                        @blur="field.handleBlur"
                        @update:model-value="
                            field.handleChange(toVideoNumber($event))
                        "
                    />
                </AppField>
            </form.Field>
        </div>
    </FormSheet>
</template>
