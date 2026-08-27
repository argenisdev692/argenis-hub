<script setup lang="ts">
import { cn } from '@/lib/utils';

/**
 * The list-surface header: search, filters, bulk actions, primary actions.
 *
 * Deliberately a dumb layout shell. It owns no filter state, no query, no
 * permissions — the module keeps all of that (see the Services `Index.vue`).
 * All this contributes is the responsive flex arrangement and the "N selected"
 * affordance that reveals the `bulk` slot, so every index page composes the
 * same four regions instead of re-deriving the wrapper each time.
 */

const {
    selectedCount = 0,
    selectionLabel = 'selected',
    ariaLabel = 'Filters and actions',
    class: className,
} = defineProps<{
    /** Drives the "N selected" counter and the `bulk` slot's visibility. */
    selectedCount?: number;
    /** Trailing word after the count — "3 services selected". */
    selectionLabel?: string;
    ariaLabel?: string;
    class?: string;
}>();

defineSlots<{
    /** Free-text search control. */
    search?: () => unknown;
    /** Date range, status select — anything that narrows the list. */
    filters?: () => unknown;
    /** Actions on the current selection. Rendered only while `selectedCount > 0`. */
    bulk?: (props: { count: number }) => unknown;
    /** Primary actions — create, export. Always rendered. */
    actions?: () => unknown;
}>();
</script>

<template>
    <div
        role="group"
        :aria-label="ariaLabel"
        :class="cn('flex flex-wrap items-center gap-3', className)"
    >
        <slot name="search" />
        <slot name="filters" />

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <Transition
                enter-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-active-class="transition-opacity duration-150"
                leave-to-class="opacity-0"
            >
                <div v-if="selectedCount > 0" class="flex items-center gap-2">
                    <span
                        class="text-sm text-muted-foreground tabular-nums"
                        role="status"
                        aria-live="polite"
                    >
                        {{ selectedCount }} {{ selectionLabel }}
                    </span>
                    <slot name="bulk" :count="selectedCount" />
                </div>
            </Transition>

            <slot name="actions" />
        </div>
    </div>
</template>
