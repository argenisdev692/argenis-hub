<script setup lang="ts">
import { useId } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Shared chrome for every dashboard panel: frosted surface, heading block and
 * an optional action slot. Panels supply content; spacing lives here.
 */
const {
    title,
    description,
    class: className,
} = defineProps<{
    title: string;
    description?: string;
    class?: string;
}>();

defineSlots<{
    default: () => unknown;
    /** Right-aligned control in the header — a link, filter or menu. */
    action?: () => unknown;
}>();

const headingId = useId();
</script>

<template>
    <section
        :aria-labelledby="headingId"
        :class="
            cn(
                'flex flex-col rounded-xl border border-glass-border bg-surface-glass backdrop-blur-sm',
                className,
            )
        "
    >
        <header
            class="flex items-start justify-between gap-4 border-b border-glass-border px-5 py-4"
        >
            <div class="space-y-1">
                <h2 :id="headingId" class="font-semibold tracking-tight">
                    {{ title }}
                </h2>
                <p v-if="description" class="text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>

            <slot name="action" />
        </header>

        <div class="flex-1 p-5">
            <slot />
        </div>
    </section>
</template>
