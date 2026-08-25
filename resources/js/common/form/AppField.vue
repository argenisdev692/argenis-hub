<script setup lang="ts">
import type { AnyFieldApi } from '@tanstack/vue-form';
import { computed, useId } from 'vue';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { fieldErrorMessages, isFieldInvalid } from './useAppForm';

const {
    field,
    label,
    description,
    orientation = 'vertical',
    required = false,
} = defineProps<{
    /** The `field` object handed down by `<form.Field>`'s default slot. */
    field: AnyFieldApi;
    label?: string;
    description?: string;
    orientation?: 'vertical' | 'horizontal' | 'responsive';
    required?: boolean;
}>();

defineSlots<{
    /**
     * The control itself. Bind `v-bind="control"` to inherit the id, name and
     * ARIA wiring so every field is labelled and described consistently.
     */
    default: (props: {
        control: {
            id: string;
            name: string;
            'aria-invalid': boolean;
            'aria-describedby': string | undefined;
        };
        invalid: boolean;
    }) => unknown;
}>();

const uid = useId();
const controlId = computed(() => `${uid}-${field.name}`);
const descriptionId = computed(() => `${controlId.value}-description`);
const errorId = computed(() => `${controlId.value}-error`);

const invalid = computed(() => isFieldInvalid(field));
const errors = computed(() => fieldErrorMessages(field));

/**
 * Point `aria-describedby` at whichever of description/error is actually on
 * screen; an id that resolves to nothing is worse than no id at all.
 */
const describedBy = computed(() => {
    const ids = [
        description ? descriptionId.value : null,
        invalid.value && errors.value.length ? errorId.value : null,
    ].filter(Boolean);

    return ids.length ? ids.join(' ') : undefined;
});

const control = computed(() => ({
    id: controlId.value,
    name: field.name,
    'aria-invalid': invalid.value,
    'aria-describedby': describedBy.value,
}));
</script>

<template>
    <Field :orientation="orientation" :data-invalid="invalid">
        <FieldLabel v-if="label" :for="controlId">
            {{ label }}
            <span v-if="required" class="text-destructive" aria-hidden="true"
                >*</span
            >
            <span v-if="required" class="sr-only">(required)</span>
        </FieldLabel>

        <FieldContent v-if="orientation === 'horizontal'">
            <slot :control="control" :invalid="invalid" />
        </FieldContent>
        <slot v-else :control="control" :invalid="invalid" />

        <FieldDescription v-if="description" :id="descriptionId">
            {{ description }}
        </FieldDescription>

        <FieldError
            v-if="invalid && errors.length"
            :id="errorId"
            :errors="errors"
        />
    </Field>
</template>
