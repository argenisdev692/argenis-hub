<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/composables/useAppearance';
import { useAppearance } from '@/composables/useAppearance';

const { align = 'end' } = defineProps<{
    align?: 'start' | 'center' | 'end';
}>();

const { appearance, resolvedAppearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const satisfies ReadonlyArray<{
    value: Appearance;
    Icon: unknown;
    label: string;
}>;

/**
 * The trigger shows what is actually on screen, so "system" reads as the sun or
 * moon it currently resolves to rather than a monitor glyph that tells the user
 * nothing about the theme they are looking at.
 */
const TriggerIcon = computed(() =>
    resolvedAppearance.value === 'dark' ? Moon : Sun,
);

const triggerLabel = computed(
    () =>
        `Theme: ${options.find((option) => option.value === appearance.value)?.label ?? 'System'}`,
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" :aria-label="triggerLabel">
                <component :is="TriggerIcon" class="size-4" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent :align="align" class="min-w-36">
            <DropdownMenuItem
                v-for="option in options"
                :key="option.value"
                :data-active="appearance === option.value"
                class="data-[active=true]:bg-accent"
                @select="updateAppearance(option.value)"
            >
                <component :is="option.Icon" class="size-4" />
                {{ option.label }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
