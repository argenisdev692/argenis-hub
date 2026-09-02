<script setup lang="ts">
import { AppField, FormDialog, TextField } from '@/common/form';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { useAvailabilityRuleForm } from '../composables/useAvailabilityRuleForm';
import {
    DAY_OF_WEEK_VALUES,
    dayOfWeekLabel,
    isDayOfWeek,
} from '../helpers/availabilityPresentation';
import type { AvailabilityRule } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `rule` is `null` in create mode; `useAvailabilityRuleForm` reads it at submit
 * time to decide between `store` and `update`, so this component only has to
 * carry the prop through, not branch on it itself.
 */
const { rule = null } = defineProps<{
    rule?: AvailabilityRule | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useAvailabilityRuleForm({ open, rule: () => rule });
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="rule ? 'Edit availability rule' : 'New availability rule'"
        description="One slot on one weekday, repeated every week. Date exceptions and national holidays override it."
        submit-label="Save"
        content-class="sm:max-w-lg"
    >
        <div class="grid gap-4">
            <form.Field name="day_of_week" #default="{ field }">
                <AppField
                    :field="field"
                    label="Weekday"
                    required
                    description="The rule repeats on this day every week."
                    #default="{ control }"
                >
                    <!--
                        `Select` models its value as a string, while the column
                        is an integer (`between:0,6`). Converted at the boundary
                        rather than storing the string, so the payload matches
                        what `AvailabilityRuleData` validates.
                    -->
                    <Select
                        :model-value="String(field.state.value)"
                        @update:model-value="
                            (value) => {
                                const day = Number(value);
                                if (isDayOfWeek(day)) {
                                    field.handleChange(day);
                                }
                            }
                        "
                    >
                        <SelectTrigger v-bind="control" class="w-full">
                            <SelectValue placeholder="Select a weekday" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="day in DAY_OF_WEEK_VALUES"
                                :key="day"
                                :value="String(day)"
                            >
                                {{ dayOfWeekLabel(day) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </AppField>
            </form.Field>

            <div class="grid gap-4 sm:grid-cols-2">
                <form.Field name="start_time" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Start time"
                        type="time"
                        required
                        description="24-hour, e.g. 09:00."
                    />
                </form.Field>

                <form.Field name="end_time" #default="{ field }">
                    <TextField
                        :field="field"
                        label="End time"
                        type="time"
                        required
                        description="Must be later than the start time."
                    />
                </form.Field>
            </div>

            <form.Field name="is_available" #default="{ field }">
                <AppField
                    :field="field"
                    label="Available"
                    orientation="horizontal"
                    description="Off marks the slot as explicitly unavailable. Only available slots are checked for overlaps."
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
        </div>
    </FormDialog>
</template>
