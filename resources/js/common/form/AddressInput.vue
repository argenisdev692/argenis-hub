<script setup lang="ts">
import { Loader2Icon, MapPinIcon } from '@lucide/vue';
import { refDebounced } from '@vueuse/core';
import { ComboboxInput as ComboboxInputPrimitive } from 'reka-ui';
import { computed, onWatcherCleanup, ref, watch } from 'vue';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { PlaceSuggestion, ResolvedAddress } from './useGooglePlaces';
import { useGooglePlaces } from './useGooglePlaces';

const {
    placeholder = 'Start typing an address…',
    disabled = false,
    countries = [],
    addressesOnly = true,
    minQueryLength = 3,
    debounceMs = 300,
    class: className,
} = defineProps<{
    placeholder?: string;
    disabled?: boolean;
    /** ISO 3166-1 alpha-2 codes, max 5 — e.g. `['us', 'ca']`. */
    countries?: string[];
    /** Restrict to street addresses; turn off to allow businesses and landmarks. */
    addressesOnly?: boolean;
    minQueryLength?: number;
    debounceMs?: number;
    class?: string;
}>();

/** The structured result. Null until the user picks a suggestion. */
const model = defineModel<ResolvedAddress | null>({ default: null });

/**
 * The raw text in the box, kept separate so a user can still save an address
 * Google does not recognise instead of being forced into a suggestion.
 */
const text = defineModel<string>('text', { default: '' });

const { search, resolve, isConfigured } = useGooglePlaces();

const open = ref(false);
const suggestions = ref<PlaceSuggestion[]>([]);
const isLoading = ref(false);
const errorMessage = ref<string | null>(null);
const debouncedText = refDebounced(text, debounceMs);

watch(model, (value) => {
    if (value && value.formatted_address !== text.value) {
        text.value = value.formatted_address;
    }
});

watch(debouncedText, (query) => {
    if (!isConfigured() || query.length < minQueryLength) {
        suggestions.value = [];
        open.value = false;

        return;
    }

    // The picked address is already in the box — don't re-query it.
    if (model.value && model.value.formatted_address === query) {
        return;
    }

    let cancelled = false;
    isLoading.value = true;
    errorMessage.value = null;

    search(query, {
        countries,
        includedPrimaryTypes: addressesOnly ? ['address'] : undefined,
    })
        .then((results) => {
            if (cancelled) {
                return;
            }

            suggestions.value = results;
            open.value = results.length > 0;
        })
        .catch((error: unknown) => {
            if (cancelled) {
                return;
            }

            suggestions.value = [];
            errorMessage.value =
                error instanceof Error
                    ? error.message
                    : 'Address lookup failed.';
        })
        .finally(() => {
            if (!cancelled) {
                isLoading.value = false;
            }
        });

    onWatcherCleanup(() => {
        cancelled = true;
    });
});

async function onSelect(suggestion: PlaceSuggestion): Promise<void> {
    isLoading.value = true;

    try {
        const resolved = await resolve(suggestion);
        model.value = resolved;
        text.value = resolved.formatted_address;
        open.value = false;
    } catch {
        errorMessage.value = 'Could not load details for that address.';
    } finally {
        isLoading.value = false;
    }
}

function onInput(event: Event): void {
    text.value = (event.target as HTMLInputElement).value;

    // Typing after a pick invalidates the structured value — the two must never
    // drift apart, or you save one address while showing another.
    if (model.value) {
        model.value = null;
    }
}

const showHint = computed(() => !isConfigured() && !disabled);
</script>

<template>
    <div :class="cn('w-full', className)">
        <Combobox v-model:open="open" ignore-filter :disabled="disabled">
            <ComboboxAnchor as-child>
                <div class="relative">
                    <MapPinIcon
                        class="pointer-events-none absolute inset-y-0 left-3 my-auto size-4 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <ComboboxInputPrimitive as-child>
                        <Input
                            :model-value="text"
                            type="text"
                            autocomplete="street-address"
                            :placeholder="placeholder"
                            :disabled="disabled"
                            class="pr-9 pl-9"
                            v-bind="$attrs"
                            @input="onInput"
                        />
                    </ComboboxInputPrimitive>
                    <Loader2Icon
                        v-if="isLoading"
                        class="absolute inset-y-0 right-3 my-auto size-4 animate-spin text-muted-foreground"
                        aria-hidden="true"
                    />
                </div>
            </ComboboxAnchor>

            <ComboboxList class="w-(--reka-combobox-trigger-width) p-1">
                <ComboboxEmpty>No matching address.</ComboboxEmpty>
                <ComboboxGroup>
                    <ComboboxItem
                        v-for="suggestion in suggestions"
                        :key="suggestion.placeId"
                        :value="suggestion.placeId"
                        class="flex-col items-start gap-0"
                        @select="onSelect(suggestion)"
                    >
                        <span class="text-sm">{{
                            suggestion.primaryText
                        }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ suggestion.secondaryText }}
                        </span>
                    </ComboboxItem>
                </ComboboxGroup>
            </ComboboxList>
        </Combobox>

        <p
            v-if="errorMessage"
            class="mt-1.5 text-sm text-destructive"
            role="alert"
        >
            {{ errorMessage }}
        </p>
        <p v-else-if="showHint" class="mt-1.5 text-sm text-muted-foreground">
            Address suggestions are unavailable — enter the address manually.
        </p>
    </div>
</template>
