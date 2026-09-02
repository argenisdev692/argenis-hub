<script setup lang="ts">
import {
    AppField,
    DatePickerInput,
    FormDialog,
    TextField,
} from '@/common/form';
import { Switch } from '@/components/ui/switch';
import { useAvailabilityExceptionForm } from '../composables/useAvailabilityExceptionForm';
import { todayIso } from '../helpers/availabilityPresentation';
import type { AvailabilityException } from '../types';

/**
 * One dialog for both create and edit.
 *
 * `exception` is `null` in create mode; `useAvailabilityExceptionForm` reads it
 * at submit time to decide between `store` and `update`, so this component only
 * has to carry the prop through, not branch on it itself.
 */
const { exception = null } = defineProps<{
    exception?: AvailabilityException | null;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useAvailabilityExceptionForm({
    open,
    exception: () => exception,
});

/**
 * Drives whether the hours are rendered at all. Read through `useStore` rather
 * than off `exception`, because the operator can flip the switch inside an open
 * dialog and the fields have to follow the live value, not the seeded one.
 */
const isOpenDay = form.useStore((state) => state.values.is_available);
</script>

<template>
    <FormDialog
        v-model:open="open"
        :form="form"
        :title="exception ? 'Edit date exception' : 'New date exception'"
        description="Overrides the weekly template for one specific date — a closure, or a day open outside the usual hours."
        submit-label="Save"
        content-class="sm:max-w-lg"
    >
        <div class="grid gap-4">
            <!--
                A holiday row is rebuilt by `HolidayMaterializer` on the yearly
                sync and on a country change, so an edit to one does not
                survive. Said before the operator starts typing, not after.
            -->
            <p
                v-if="exception?.source === 'holiday'"
                class="rounded-lg border border-border bg-muted/40 px-3 py-2 text-sm text-muted-foreground"
            >
                This exception was materialised from the national holiday
                calendar. Edits are overwritten the next time holidays are
                synced — create a manual exception instead if the change needs
                to stick.
            </p>

            <form.Field name="date" #default="{ field }">
                <AppField
                    :field="field"
                    label="Date"
                    required
                    :description="
                        exception
                            ? 'Only one active exception may exist per date.'
                            : 'Today or later. Only one active exception may exist per date.'
                    "
                    #default="{ control }"
                >
                    <!--
                        `min-value` only in create mode, mirroring the backend's
                        `after_or_equal:today`, which it relaxes on edit so an
                        already-past exception stays correctable.
                    -->
                    <DatePickerInput
                        v-bind="control"
                        class="w-full"
                        :clearable="false"
                        :min-value="exception ? undefined : todayIso()"
                        :model-value="field.state.value"
                        @update:model-value="
                            (value) => field.handleChange(value ?? '')
                        "
                    />
                </AppField>
            </form.Field>

            <form.Field name="is_available" #default="{ field }">
                <AppField
                    :field="field"
                    label="Open this day"
                    orientation="horizontal"
                    description="Off closes the day entirely. On forces it open with its own hours, ignoring the weekly template."
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
                Shown only for a forced-open day: a closure needs no hours, the
                server ignores any that are sent, and rendering two dead inputs
                under a switch that just closed the day only invites filling
                them in.
            -->
            <div v-if="isOpenDay" class="grid gap-4 sm:grid-cols-2">
                <form.Field name="start_time" #default="{ field }">
                    <TextField
                        :field="field"
                        label="Start time"
                        type="time"
                        required
                        description="24-hour, e.g. 10:00."
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

            <form.Field name="reason" #default="{ field }">
                <TextField
                    :field="field"
                    label="Reason"
                    multiline
                    :rows="2"
                    placeholder="Public holiday, team offsite, …"
                    description="Optional, up to 255 characters. This is the text the list search matches on."
                />
            </form.Field>
        </div>
    </FormDialog>
</template>
