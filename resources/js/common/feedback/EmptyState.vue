<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { cn } from '@/lib/utils';

/**
 * The one empty state.
 *
 * A new account sees zeros before it sees data, so "nothing here yet" is an
 * onboarding surface, not an error: it always names what is missing and, where
 * there is one, offers the action that fills it.
 */
const {
    title,
    description,
    icon = Inbox,
    class: className,
} = defineProps<{
    title: string;
    description?: string;
    icon?: LucideIcon;
    class?: string;
}>();

defineSlots<{
    /** Primary action — the thing that makes this state go away. */
    action?: () => unknown;
}>();
</script>

<template>
    <Empty :class="cn('border-0 py-10', className)">
        <EmptyHeader>
            <EmptyMedia variant="icon">
                <component :is="icon" aria-hidden="true" />
            </EmptyMedia>
            <EmptyTitle>{{ title }}</EmptyTitle>
            <EmptyDescription v-if="description">
                {{ description }}
            </EmptyDescription>
        </EmptyHeader>

        <EmptyContent v-if="$slots.action">
            <slot name="action" />
        </EmptyContent>
    </Empty>
</template>
