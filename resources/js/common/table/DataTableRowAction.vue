<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * One icon-only row action — View, Delete, Restore — with its accessible name
 * and its hover hint decided together, so no table ships a bare icon.
 *
 * `label` feeds BOTH the `aria-label` (screen readers) and the tooltip (sighted
 * hover/focus); they serve different audiences and neither replaces the other.
 * Pass `href` for a navigation (rendered as an Inertia `Link`), otherwise listen
 * to `@click`. Authorization stays with the caller's `PermissionGuard`.
 *
 * Carries its own `TooltipProvider`: the header layout has none, and a reka
 * tooltip without a provider throws.
 */

const {
    label,
    icon,
    tooltip,
    href,
    prefetch = false,
    destructive = false,
    disabled = false,
} = defineProps<{
    /** Full accessible name, e.g. `Delete Intro to Laravel`. */
    label: string;
    icon: Component;
    /** Short visible hint; defaults to `label`. */
    tooltip?: string;
    href?: string;
    /** Prefetch the `href` page on hover — only meaningful with `href`. */
    prefetch?: boolean;
    destructive?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    click: [event: MouseEvent];
}>();
</script>

<template>
    <TooltipProvider :delay-duration="300">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button
                    v-if="href && !disabled"
                    as-child
                    variant="ghost"
                    size="icon"
                    :aria-label="label"
                >
                    <Link :href="href" :prefetch="prefetch">
                        <component
                            :is="icon"
                            class="size-4"
                            aria-hidden="true"
                        />
                    </Link>
                </Button>

                <Button
                    v-else
                    variant="ghost"
                    size="icon"
                    :aria-label="label"
                    :disabled="disabled"
                    @click="emit('click', $event)"
                >
                    <component
                        :is="icon"
                        :class="['size-4', destructive && 'text-destructive']"
                        aria-hidden="true"
                    />
                </Button>
            </TooltipTrigger>

            <TooltipContent>{{ tooltip ?? label }}</TooltipContent>
        </Tooltip>
    </TooltipProvider>
</template>
