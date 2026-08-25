<script setup lang="ts">
import { CheckIcon, ChevronsUpDownIcon, Loader2Icon, XIcon } from '@lucide/vue';
import { refDebounced } from '@vueuse/core';
import { ComboboxVirtualizer } from 'reka-ui';
import { computed, onWatcherCleanup, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxGroup,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
} from '@/components/ui/combobox';
import { cn } from '@/lib/utils';

export type FilterSelectValue = string | number;

export type FilterSelectOption = {
    value: FilterSelectValue;
    label: string;
    disabled?: boolean;
};

const {
    options = [],
    fetcher,
    multiple = false,
    placeholder = 'Select…',
    searchPlaceholder = 'Search…',
    emptyText = 'No results found.',
    clearable = true,
    disabled = false,
    minQueryLength = 0,
    debounceMs = 250,
    virtualThreshold = 100,
    maxVisibleChips = 3,
    class: className,
} = defineProps<{
    /** Static option list. Ignored when `fetcher` is supplied. */
    options?: FilterSelectOption[];
    /**
     * Remote search. When present the server owns the filtering, so the
     * client-side filter is disabled to avoid double-filtering the results.
     */
    fetcher?: (query: string) => Promise<FilterSelectOption[]>;
    multiple?: boolean;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    clearable?: boolean;
    disabled?: boolean;
    /** Wait for N characters before hitting the server. */
    minQueryLength?: number;
    debounceMs?: number;
    /** Switch to a virtualised list past this many options. */
    virtualThreshold?: number;
    /** How many chips to show before collapsing into "+N more". */
    maxVisibleChips?: number;
    class?: string;
}>();

const model = defineModel<FilterSelectValue | FilterSelectValue[] | null>({
    default: null,
});

const open = ref(false);
const searchTerm = ref('');
const debouncedTerm = refDebounced(searchTerm, debounceMs);
const remoteOptions = ref<FilterSelectOption[]>([]);
const isLoading = ref(false);

/**
 * Labels of options the user has already picked, kept even after the option
 * leaves the current result set. Without this a remote select shows a raw id
 * as soon as the search term changes — the classic async-select regression.
 */
const labelCache = ref(new Map<FilterSelectValue, string>());

const resolvedOptions = computed<FilterSelectOption[]>(() =>
    fetcher ? remoteOptions.value : options,
);

watch(
    resolvedOptions,
    (list) => {
        for (const option of list) {
            labelCache.value.set(option.value, option.label);
        }
    },
    { immediate: true },
);

watch(debouncedTerm, (query) => {
    if (!fetcher) {
        return;
    }

    if (query.length < minQueryLength) {
        remoteOptions.value = [];

        return;
    }

    const controller = new AbortController();
    isLoading.value = true;

    fetcher(query)
        .then((results) => {
            if (!controller.signal.aborted) {
                remoteOptions.value = results;
            }
        })
        .finally(() => {
            if (!controller.signal.aborted) {
                isLoading.value = false;
            }
        });

    // A newer keystroke supersedes this request — drop its result on the floor
    // so a slow early response can't overwrite a fast later one.
    onWatcherCleanup(() => controller.abort());
});

watch(open, (isOpen) => {
    if (
        isOpen &&
        fetcher &&
        minQueryLength === 0 &&
        remoteOptions.value.length === 0
    ) {
        searchTerm.value = '';
        isLoading.value = true;
        fetcher('')
            .then((results) => {
                remoteOptions.value = results;
            })
            .finally(() => {
                isLoading.value = false;
            });
    }
});

const selectedValues = computed<FilterSelectValue[]>(() => {
    if (model.value === null || model.value === undefined) {
        return [];
    }

    return Array.isArray(model.value) ? model.value : [model.value];
});

const hasSelection = computed(() => selectedValues.value.length > 0);

function labelFor(value: FilterSelectValue): string {
    return labelCache.value.get(value) ?? String(value);
}

const visibleChips = computed(() =>
    selectedValues.value.slice(0, maxVisibleChips),
);
const overflowCount = computed(() =>
    Math.max(0, selectedValues.value.length - maxVisibleChips),
);

const singleLabel = computed(() =>
    hasSelection.value ? labelFor(selectedValues.value[0]!) : '',
);

const shouldVirtualize = computed(
    () => resolvedOptions.value.length > virtualThreshold,
);

function clear(event: Event): void {
    event.stopPropagation();
    model.value = multiple ? [] : null;
}

function removeChip(value: FilterSelectValue, event: Event): void {
    event.stopPropagation();
    model.value = selectedValues.value.filter((item) => item !== value);
}
</script>

<template>
    <Combobox
        v-model="model"
        v-model:open="open"
        v-model:search-term="searchTerm"
        :multiple="multiple"
        :disabled="disabled"
        :ignore-filter="Boolean(fetcher)"
        :reset-search-term-on-blur="!fetcher"
    >
        <ComboboxAnchor as-child>
            <ComboboxTrigger as-child>
                <Button
                    variant="outline"
                    role="combobox"
                    :disabled="disabled"
                    :class="
                        cn(
                            'h-auto min-h-9 w-full justify-between gap-2 px-3 py-1.5 font-normal',
                            !hasSelection && 'text-muted-foreground',
                            className,
                        )
                    "
                    v-bind="$attrs"
                >
                    <span v-if="!hasSelection" class="truncate">{{
                        placeholder
                    }}</span>

                    <span v-else-if="!multiple" class="truncate">{{
                        singleLabel
                    }}</span>

                    <span v-else class="flex flex-wrap items-center gap-1">
                        <Badge
                            v-for="value in visibleChips"
                            :key="value"
                            variant="secondary"
                            class="gap-1 pr-1"
                        >
                            {{ labelFor(value) }}
                            <span
                                role="button"
                                tabindex="-1"
                                :aria-label="`Remove ${labelFor(value)}`"
                                class="rounded-xs hover:bg-muted-foreground/20 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                @click="removeChip(value, $event)"
                                @keydown.enter.stop.prevent="
                                    removeChip(value, $event)
                                "
                                @keydown.space.stop.prevent="
                                    removeChip(value, $event)
                                "
                            >
                                <XIcon class="size-3" />
                            </span>
                        </Badge>
                        <span
                            v-if="overflowCount"
                            class="text-xs text-muted-foreground"
                        >
                            +{{ overflowCount }} more
                        </span>
                    </span>

                    <span class="flex shrink-0 items-center gap-1">
                        <Loader2Icon
                            v-if="isLoading"
                            class="size-4 animate-spin opacity-50"
                        />
                        <span
                            v-else-if="clearable && hasSelection && !disabled"
                            role="button"
                            tabindex="-1"
                            aria-label="Clear selection"
                            class="rounded-xs opacity-50 hover:opacity-100 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            @click="clear"
                            @keydown.enter.stop.prevent="clear($event)"
                        >
                            <XIcon class="size-4" />
                        </span>
                        <ChevronsUpDownIcon class="size-4 opacity-50" />
                    </span>
                </Button>
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxList
            class="w-(--reka-combobox-trigger-width) min-w-[12rem] p-0"
        >
            <ComboboxInput :placeholder="searchPlaceholder" />

            <div
                v-if="isLoading"
                class="flex items-center gap-2 px-3 py-6 text-sm text-muted-foreground"
            >
                <Loader2Icon class="size-4 animate-spin" />
                Searching…
            </div>

            <p
                v-else-if="fetcher && searchTerm.length < minQueryLength"
                class="px-3 py-6 text-center text-sm text-muted-foreground"
            >
                Type at least {{ minQueryLength }} characters to search.
            </p>

            <template v-else>
                <ComboboxEmpty>{{ emptyText }}</ComboboxEmpty>

                <ComboboxGroup class="max-h-72 overflow-y-auto p-1">
                    <ComboboxVirtualizer
                        v-if="shouldVirtualize"
                        v-slot="{ option }"
                        :options="resolvedOptions"
                        :estimate-size="32"
                        :overscan="8"
                        :text-content="(item: FilterSelectOption) => item.label"
                    >
                        <ComboboxItem
                            :value="option.value"
                            :disabled="option.disabled"
                        >
                            {{ option.label }}
                            <ComboboxItemIndicator>
                                <CheckIcon class="ml-auto size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </ComboboxVirtualizer>

                    <template v-else>
                        <ComboboxItem
                            v-for="option in resolvedOptions"
                            :key="option.value"
                            :value="option.value"
                            :disabled="option.disabled"
                        >
                            {{ option.label }}
                            <ComboboxItemIndicator>
                                <CheckIcon class="ml-auto size-4" />
                            </ComboboxItemIndicator>
                        </ComboboxItem>
                    </template>
                </ComboboxGroup>
            </template>
        </ComboboxList>
    </Combobox>
</template>
