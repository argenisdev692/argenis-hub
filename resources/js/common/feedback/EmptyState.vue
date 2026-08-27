<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
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
    icon,
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

/**
 * `@lucide/vue` icons are bare functional components (plain functions). Used
 * directly as a prop *default* (`icon = Inbox`), Vue's prop resolution treats
 * the function as a default *factory* and invokes it as `Inbox(props)` — with
 * no render context — which throws `Cannot destructure property 'slots' of
 * 'undefined'`. Resolving the fallback here instead keeps the default off the
 * props contract and hands `<component :is>` a real component.
 */
const iconComponent = computed<LucideIcon>(() => icon ?? Inbox);
</script>

<template>
    <Empty :class="cn('border-0 py-10', className)">
        <EmptyHeader>
            <EmptyMedia variant="icon">
                <component :is="iconComponent" aria-hidden="true" />
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
