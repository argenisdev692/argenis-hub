<script setup lang="ts">
import type { AnyFieldApi } from '@tanstack/vue-form';
import {
    TagsInput,
    TagsInputInput,
    TagsInputItem,
    TagsInputItemDelete,
    TagsInputItemText,
} from '@/components/ui/tags-input';
import AppField from './AppField.vue';

/**
 * `AppField` + `TagsInput`, for a `string[]` field edited one entry at a time
 * (Enter adds, Backspace removes). The `TextField` of list fields: label,
 * description, error and ARIA wiring decided once.
 */
const {
    field,
    label,
    description = 'Press Enter after each entry.',
    placeholder,
    required = false,
} = defineProps<{
    /** The `field` object from `<form.Field>`'s default slot. */
    field: AnyFieldApi;
    label: string;
    description?: string;
    placeholder?: string;
    required?: boolean;
}>();

/** The schema models the field as `string[]`; anything else renders empty. */
function currentValue(): string[] {
    const value: unknown = field.state.value;

    return Array.isArray(value) ? value.map(String) : [];
}

/** reka's `TagsInput` emits `AcceptableInputValue[]`; this field holds strings. */
function onChange(value: unknown[]): void {
    field.handleChange(value.map(String));
}
</script>

<template>
    <AppField
        :field="field"
        :label="label"
        :description="description"
        :required="required"
        #default="{ control }"
    >
        <TagsInput
            :model-value="currentValue()"
            @update:model-value="onChange"
            @blur="field.handleBlur"
        >
            <TagsInputItem
                v-for="item in currentValue()"
                :key="item"
                :value="item"
            >
                <TagsInputItemText />
                <TagsInputItemDelete />
            </TagsInputItem>

            <TagsInputInput
                :id="control.id"
                :aria-invalid="control['aria-invalid']"
                :aria-describedby="control['aria-describedby']"
                :placeholder="placeholder"
            />
        </TagsInput>
    </AppField>
</template>
