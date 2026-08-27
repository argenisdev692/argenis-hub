<script setup lang="ts">
import { SearchIcon, XIcon } from '@lucide/vue';
import { onWatcherCleanup, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Debounced search box. Owns the "wait for the typing to stop" timer that was
 * previously copy-pasted into every index page (`setTimeout` + `onWatcherCleanup`
 * in `Index.vue`); the page now just binds `v-model` and reads a committed term.
 */

const {
    placeholder = 'Search…',
    ariaLabel = 'Search',
    debounce = 300,
    disabled = false,
    class: className,
} = defineProps<{
    placeholder?: string;
    ariaLabel?: string;
    /** Milliseconds to wait after the last keystroke before committing. */
    debounce?: number;
    disabled?: boolean;
    class?: string;
}>();

/** The committed term — changes `debounce` ms after the user stops typing. */
const model = defineModel<string>({ default: '' });

/** What the field shows while typing, before the debounce fires. */
const draft = ref(model.value);

// Follow the model when it is reset from outside (a "clear filters" button, a
// URL-synced restore); guarding on inequality avoids a feedback loop.
watch(model, (value) => {
    if (value !== draft.value) {
        draft.value = value;
    }
});

watch(draft, (value) => {
    const timer = setTimeout(() => {
        model.value = value;
    }, debounce);

    onWatcherCleanup(() => clearTimeout(timer));
});

function clear(): void {
    draft.value = '';
    model.value = '';
}
</script>

<template>
    <div :class="cn('relative w-full max-w-xs', className)">
        <SearchIcon
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
        />
        <Input
            v-model="draft"
            type="search"
            :placeholder="placeholder"
            :aria-label="ariaLabel"
            :disabled="disabled"
            :class="
                cn(
                    'pl-9 [&::-webkit-search-cancel-button]:appearance-none',
                    draft && 'pr-9',
                )
            "
        />
        <button
            v-if="draft && !disabled"
            type="button"
            aria-label="Clear search"
            class="absolute top-1/2 right-2 -translate-y-1/2 rounded-xs p-0.5 text-muted-foreground opacity-70 transition-opacity hover:opacity-100 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            @click="clear"
        >
            <XIcon class="size-4" />
        </button>
    </div>
</template>
