<script setup lang="ts">
import type { CalendarDate, DateValue } from '@internationalized/date';
import {
    DateFormatter,
    getLocalTimeZone,
    parseDate,
} from '@internationalized/date';
import { CalendarIcon, XIcon } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { cn } from '@/lib/utils';
import type { DateRangePreset } from './dateRangePresets';
import {
    DATE_RANGE_PRESETS,
    matchingPreset,
    todayInLocalZone,
} from './dateRangePresets';
import type { DateRange } from './types';

/**
 * A `created_at` window as two ISO `YYYY-MM-DD` strings — the exact wire shape
 * `ServiceFilterData::$dateFrom` / `$dateTo` (and every other module's filter
 * DTO) expects. The backend owns the "from ≤ to" invariant; reka's calendar
 * enforces it in the UI, so this component does not re-validate.
 */

const {
    placeholder = 'Any date',
    locale = 'en',
    disabled = false,
    clearable = true,
    numberOfMonths = 2,
    presets = false,
    disableFuture = false,
    class: className,
} = defineProps<{
    placeholder?: string;
    locale?: string;
    disabled?: boolean;
    clearable?: boolean;
    numberOfMonths?: number;
    /** Show the Today / Last 7 days / This month… shortcuts beside the calendar. */
    presets?: boolean;
    /** Block days after today — a `created_at` window has nothing in the future. */
    disableFuture?: boolean;
    class?: string;
}>();

const model = defineModel<DateRange>({
    default: () => ({ from: null, to: null }),
});

const open = ref(false);

const formatter = new DateFormatter(locale, { dateStyle: 'medium' });

type CalendarRange = {
    start: DateValue | undefined;
    end: DateValue | undefined;
};

function toCalendarDate(iso: string | null): CalendarDate | undefined {
    if (!iso) {
        return undefined;
    }

    try {
        return parseDate(iso);
    } catch {
        return undefined;
    }
}

const calendarValue = computed<CalendarRange>(() => ({
    start: toCalendarDate(model.value.from),
    end: toCalendarDate(model.value.to),
}));

const hasValue = computed(
    () => model.value.from !== null || model.value.to !== null,
);

function label(iso: string | null): string {
    const date = toCalendarDate(iso);

    return date ? formatter.format(date.toDate(getLocalTimeZone())) : '…';
}

const displayValue = computed(() => {
    if (!hasValue.value) {
        return '';
    }

    const preset = presets ? matchingPreset(model.value) : undefined;

    return (
        preset?.label ?? `${label(model.value.from)} – ${label(model.value.to)}`
    );
});

/** Read on open, so a tab left open past midnight still caps at the real today. */
const maxValue = computed(() =>
    disableFuture && open.value ? todayInLocalZone() : undefined,
);

function applyPreset(preset: DateRangePreset): void {
    model.value = preset.resolve(todayInLocalZone());
    open.value = false;
}

function onSelect(value: CalendarRange | null | undefined): void {
    model.value = {
        from: value?.start ? value.start.toString() : null,
        to: value?.end ? value.end.toString() : null,
    };

    if (value?.start && value.end) {
        open.value = false;
    }
}

function clear(event: Event): void {
    event.stopPropagation();
    model.value = { from: null, to: null };
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
                        'w-full justify-start gap-2 px-3 font-normal sm:w-auto',
                        !hasValue && 'text-muted-foreground',
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
                    v-if="clearable && hasValue && !disabled"
                    role="button"
                    tabindex="-1"
                    aria-label="Clear date range"
                    class="rounded-xs opacity-50 hover:opacity-100 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    @click="clear"
                    @keydown.enter.stop.prevent="clear($event)"
                >
                    <XIcon class="size-4" />
                </span>
            </Button>
        </PopoverTrigger>

        <PopoverContent
            class="flex w-auto flex-col p-0 sm:flex-row"
            align="start"
        >
            <div
                v-if="presets"
                role="group"
                aria-label="Date range presets"
                class="flex flex-wrap gap-1 border-b border-border p-2 sm:w-36 sm:flex-col sm:flex-nowrap sm:border-r sm:border-b-0"
            >
                <Button
                    v-for="preset in DATE_RANGE_PRESETS"
                    :key="preset.label"
                    variant="ghost"
                    size="sm"
                    class="justify-start"
                    @click="applyPreset(preset)"
                >
                    {{ preset.label }}
                </Button>
            </div>

            <RangeCalendar
                :model-value="calendarValue"
                :number-of-months="numberOfMonths"
                :locale="locale"
                :max-value="maxValue"
                initial-focus
                @update:model-value="onSelect"
            />
        </PopoverContent>
    </Popover>
</template>
