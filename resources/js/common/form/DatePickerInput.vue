<script setup lang="ts">
import type { DateValue, CalendarDate } from '@internationalized/date';
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
} from '@internationalized/date';
import { CalendarIcon, XIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

const {
    placeholder = 'Pick a date',
    disabled = false,
    clearable = true,
    locale = 'en',
    minValue,
    maxValue,
    class: className,
} = defineProps<{
    placeholder?: string;
    disabled?: boolean;
    clearable?: boolean;
    locale?: string;
    /** ISO `YYYY-MM-DD`. */
    minValue?: string;
    /** ISO `YYYY-MM-DD`. */
    maxValue?: string;
    class?: string;
}>();

/**
 * ISO `YYYY-MM-DD`, which is what Laravel's `date` validation rule and an
 * Eloquent `date` cast both expect. Deliberately not a JS `Date` — that drags
 * a timezone into a value that has none, and shifts the day across UTC.
 */
const model = defineModel<string | null>({ default: null });

const open = ref(false);

const formatter = new DateFormatter(locale, { dateStyle: 'long' });

function toCalendarDate(
    iso: string | null | undefined,
): CalendarDate | undefined {
    if (!iso) {
        return undefined;
    }

    try {
        return parseDate(iso);
    } catch {
        return undefined;
    }
}

const calendarValue = computed<DateValue | undefined>(() =>
    toCalendarDate(model.value),
);

const displayValue = computed(() =>
    calendarValue.value
        ? formatter.format(calendarValue.value.toDate(getLocalTimeZone()))
        : '',
);

function onSelect(value: DateValue | undefined): void {
    model.value = value ? value.toString() : null;
    open.value = false;
}

function clear(event: Event): void {
    event.stopPropagation();
    model.value = null;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                variant="outline"
                :disabled="disabled"
                :class="
                    cn(
                        'w-full justify-start gap-2 px-3 font-normal',
                        !model && 'text-muted-foreground',
                        className,
                    )
                "
                v-bind="$attrs"
            >
                <CalendarIcon
                    class="size-4 shrink-0 opacity-50"
                    aria-hidden="true"
                />
                <span class="flex-1 truncate text-left">
                    {{ displayValue || placeholder }}
                </span>
                <span
                    v-if="clearable && model && !disabled"
                    role="button"
                    tabindex="-1"
                    aria-label="Clear date"
                    class="rounded-xs opacity-50 hover:opacity-100 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    @click="clear"
                    @keydown.enter.stop.prevent="clear($event)"
                >
                    <XIcon class="size-4" />
                </span>
            </Button>
        </PopoverTrigger>

        <PopoverContent class="w-auto p-0" align="start">
            <Calendar
                :model-value="calendarValue"
                :locale="locale"
                :min-value="toCalendarDate(minValue)"
                :max-value="toCalendarDate(maxValue)"
                initial-focus
                @update:model-value="onSelect"
            />
        </PopoverContent>
    </Popover>
</template>
