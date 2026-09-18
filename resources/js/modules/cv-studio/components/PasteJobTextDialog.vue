<script setup lang="ts">
import { computed, watch } from 'vue';
import {
    applyServerErrors,
    FormDialog,
    TextField,
    useAppForm,
} from '@/common/form';
import { HttpError } from '@/lib/http';
import { usePasteJobText } from '../composables/useReferences';
import {
    emptyPasteJobTextValues,
    PASTE_MIN_LENGTH,
    pasteJobTextSchema,
} from '../schemas/pasteJobTextSchema';
import type { StudioReference } from '../types';

/**
 * Paste the text of a link-only posting so it is scored like any other.
 * Nothing is ever fetched from the original site (OWASP §15 — SSRF): the
 * user reads it in their own browser and supplies the text here.
 */
const { reference } = defineProps<{
    reference: StudioReference | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const pasteText = usePasteJobText();

const form = useAppForm({
    defaultValues: emptyPasteJobTextValues(),
    schema: pasteJobTextSchema,
    async onSubmit(values) {
        if (!reference) {
            return;
        }

        try {
            await pasteText.mutateAsync({
                uuid: reference.uuid,
                text: values.text.trim(),
            });
            open.value = false;
        } catch (error) {
            if (error instanceof HttpError && error.errors) {
                applyServerErrors(
                    form,
                    Object.fromEntries(
                        Object.entries(error.errors).map(([key, messages]) => [
                            key,
                            messages[0] ?? '',
                        ]),
                    ),
                );
            }
        }
    },
});

/** Live counter — the hint under the field, so the minimum is never a surprise. */
const length = form.useStore((state) => state.values.text.trim().length);
const counter = computed(
    () =>
        `${length.value.toLocaleString()} / ${PASTE_MIN_LENGTH} characters minimum.`,
);

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset(emptyPasteJobTextValues());
    }
});
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        title="Paste the posting text"
        :description="
            reference
                ? `Paste exactly what you read for “${reference.title}”. It is scored like any other posting and marked as supplied by you.`
                : undefined
        "
        submit-label="Save and score"
        content-class="sm:max-w-2xl"
    >
        <form.Field name="text" #default="{ field }">
            <TextField
                :field="field"
                label="Posting text"
                multiline
                :rows="12"
                required
                placeholder="Paste the full posting text here…"
                :description="counter"
            />
        </form.Field>
    </FormDialog>
</template>
