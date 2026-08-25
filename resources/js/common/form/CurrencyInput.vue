<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

const {
    currency = 'USD',
    locale = 'en-US',
    disabled = false,
    placeholder = '0.00',
    /**
     * Store as integer minor units (cents). Strongly preferred — floats lose
     * money. Turn off only when the backend column really is a decimal.
     */
    minorUnits = true,
    min,
    max,
    class: className,
} = defineProps<{
    currency?: string;
    locale?: string;
    disabled?: boolean;
    placeholder?: string;
    minorUnits?: boolean;
    min?: number;
    max?: number;
    class?: string;
}>();

const model = defineModel<number | null>({ default: null });

const isFocused = ref(false);
const draft = ref('');

const fractionDigits = computed(() => {
    const parts = new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    }).resolvedOptions();

    return parts.maximumFractionDigits ?? 2;
});

const factor = computed(() => 10 ** fractionDigits.value);

const symbol = computed(() => {
    const parts = new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    }).formatToParts(0);

    return parts.find((part) => part.type === 'currency')?.value ?? currency;
});

/** Model units → the major-unit number a human types. */
function toMajor(value: number | null): number | null {
    if (value === null) {
        return null;
    }

    return minorUnits ? value / factor.value : value;
}

/** Human major units → whatever the model stores. */
function toModel(value: number): number {
    return minorUnits ? Math.round(value * factor.value) : value;
}

const formatted = computed(() => {
    const major = toMajor(model.value);

    if (major === null) {
        return '';
    }

    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency,
    }).format(major);
});

/**
 * While focused the user sees a plain editable number; on blur it snaps to the
 * fully formatted currency. Formatting mid-keystroke fights the caret.
 */
const displayValue = computed(() =>
    isFocused.value ? draft.value : formatted.value,
);

watch(model, (value) => {
    if (!isFocused.value) {
        const major = toMajor(value);
        draft.value = major === null ? '' : String(major);
    }
});

function onFocus(): void {
    const major = toMajor(model.value);
    draft.value = major === null ? '' : String(major);
    isFocused.value = true;
}

function onInput(event: Event): void {
    const raw = (event.target as HTMLInputElement).value;

    // Keep digits, one decimal separator and a leading minus.
    draft.value = raw.replace(/[^\d.,-]/g, '');
}

function onBlur(): void {
    isFocused.value = false;

    const normalised = draft.value.replace(/,/g, '.').trim();

    if (!normalised) {
        model.value = null;

        return;
    }

    const parsed = Number.parseFloat(normalised);

    if (Number.isNaN(parsed)) {
        model.value = null;

        return;
    }

    let next = toModel(parsed);

    if (min !== undefined) {
        next = Math.max(next, min);
    }

    if (max !== undefined) {
        next = Math.min(next, max);
    }

    model.value = next;
}
</script>

<template>
    <div class="relative">
        <span
            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground"
            aria-hidden="true"
        >
            {{ symbol }}
        </span>
        <Input
            :model-value="displayValue"
            type="text"
            inputmode="decimal"
            :placeholder="placeholder"
            :disabled="disabled"
            :class="
                cn(
                    'pl-[calc(var(--spacing)*3+2.5ch)] text-right tabular-nums',
                    className,
                )
            "
            v-bind="$attrs"
            @focus="onFocus"
            @input="onInput"
            @blur="onBlur"
        />
    </div>
</template>
