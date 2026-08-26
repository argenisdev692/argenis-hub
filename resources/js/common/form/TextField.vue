<script setup lang="ts">
import type { AnyFieldApi } from '@tanstack/vue-form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppField from './AppField.vue';

/**
 * `AppField` + the control, for the ordinary case of a string field.
 *
 * The overwhelming majority of fields in this app are "a label, an input, and
 * an error" — writing that out is eighteen lines of identical markup each time,
 * which is how a placeholder ends up on the wrong field or an `@blur` goes
 * missing on one input out of thirty.
 *
 * It takes the `field` object, not the form, so the parent keeps
 * `<form.Field>` where `form` is concretely typed and Vue can still infer the
 * slot. Handing the form down here instead would flatten it to `AnyFormApi` and
 * take every field name's type with it.
 *
 * Reach past this for anything richer — `PhoneInput`, `FilterSelect`,
 * `DatePickerInput` and friends all compose with `AppField` directly.
 */
const {
    field,
    label,
    description,
    placeholder,
    type = 'text',
    multiline = false,
    rows = 3,
    required = false,
    autocomplete,
    inputmode,
} = defineProps<{
    /** The `field` object from `<form.Field>`'s default slot. */
    field: AnyFieldApi;
    label: string;
    description?: string;
    placeholder?: string;
    /** Native input type. Ignored when `multiline` is set. */
    type?: string;
    multiline?: boolean;
    rows?: number;
    required?: boolean;
    autocomplete?: string;
    inputmode?: 'text' | 'email' | 'tel' | 'url' | 'numeric' | 'decimal';
}>();

/**
 * The schema models every text field as a string, so a null that slipped
 * through from the API renders as an empty box rather than the text "null".
 */
function currentValue(): string {
    return String(field.state.value ?? '');
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
        <Textarea
            v-if="multiline"
            v-bind="control"
            :model-value="currentValue()"
            :rows="rows"
            :placeholder="placeholder"
            @blur="field.handleBlur"
            @update:model-value="field.handleChange(String($event))"
        />
        <Input
            v-else
            v-bind="control"
            :type="type"
            :model-value="currentValue()"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            @blur="field.handleBlur"
            @update:model-value="field.handleChange(String($event))"
        />
    </AppField>
</template>
