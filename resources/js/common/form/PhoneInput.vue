<script setup lang="ts">
import type { CountryCode } from 'libphonenumber-js';
import {
    AsYouType,
    getCountries,
    getCountryCallingCode,
    parsePhoneNumberFromString,
} from 'libphonenumber-js';
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import FilterSelect from './FilterSelect.vue';
import type { FilterSelectOption } from './FilterSelect.vue';

const {
    defaultCountry = 'US',
    placeholder = 'Phone number',
    disabled = false,
    /**
     * Restrict the country list. Leave empty for all 245 that
     * libphonenumber-js carries metadata for.
     */
    onlyCountries = [],
    class: className,
} = defineProps<{
    defaultCountry?: CountryCode;
    placeholder?: string;
    disabled?: boolean;
    onlyCountries?: CountryCode[];
    class?: string;
}>();

/** Always E.164 (`+14155550123`) — the format `propaganistas/laravel-phone` expects. */
const model = defineModel<string | null>({ default: null });

const country = ref<CountryCode>(defaultCountry);
const national = ref('');

const regionNames = new Intl.DisplayNames(['en'], { type: 'region' });

const countryOptions = computed<FilterSelectOption[]>(() => {
    const list = onlyCountries.length ? onlyCountries : getCountries();

    return list
        .map((code) => ({
            value: code,
            label: `${regionNames.of(code) ?? code} +${getCountryCallingCode(code)}`,
        }))
        .sort((a, b) => a.label.localeCompare(b.label));
});

/**
 * Seed the two controls from an existing E.164 value (edit forms), without
 * echoing back into the model and clobbering what the parent passed in.
 */
watch(
    model,
    (value) => {
        if (!value) {
            return;
        }

        const parsed = parsePhoneNumberFromString(value);

        if (!parsed) {
            return;
        }

        if (parsed.country) {
            country.value = parsed.country;
        }

        const formatted = new AsYouType(parsed.country).input(
            parsed.nationalNumber.toString(),
        );

        if (formatted !== national.value) {
            national.value = formatted;
        }
    },
    { immediate: true },
);

function emitModel(): void {
    const digits = national.value.replace(/\D/g, '');

    if (!digits) {
        model.value = null;

        return;
    }

    const parsed = parsePhoneNumberFromString(national.value, country.value);
    model.value = parsed
        ? parsed.number
        : `+${getCountryCallingCode(country.value)}${digits}`;
}

function onInput(event: Event): void {
    const raw = (event.target as HTMLInputElement).value;

    // Re-running AsYouType from scratch each keystroke keeps the caret sane and
    // lets the user delete a separator without it snapping straight back.
    national.value = new AsYouType(country.value).input(raw);
    emitModel();
}

watch(country, () => {
    national.value = new AsYouType(country.value).input(
        national.value.replace(/\D/g, ''),
    );
    emitModel();
});

const dialCode = computed(() => `+${getCountryCallingCode(country.value)}`);

/** FilterSelect speaks `string | number`; CountryCode is a narrowed string. */
const countryProxy = computed({
    get: (): string => country.value,
    set: (value: string) => {
        country.value = value as CountryCode;
    },
});
</script>

<template>
    <div :class="cn('flex gap-2', className)">
        <FilterSelect
            v-model="countryProxy"
            :options="countryOptions"
            :disabled="disabled"
            placeholder="Country"
            search-placeholder="Search country…"
            :clearable="false"
            aria-label="Country calling code"
            class="w-[11rem] shrink-0"
        />

        <div class="relative flex-1">
            <span
                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-muted-foreground"
                aria-hidden="true"
            >
                {{ dialCode }}
            </span>
            <Input
                :model-value="national"
                type="tel"
                inputmode="tel"
                autocomplete="tel-national"
                :placeholder="placeholder"
                :disabled="disabled"
                :class="cn('pl-[calc(var(--spacing)*3+3.5ch)]')"
                v-bind="$attrs"
                @input="onInput"
            />
        </div>
    </div>
</template>
