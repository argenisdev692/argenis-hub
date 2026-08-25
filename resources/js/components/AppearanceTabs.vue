<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';

const { appearance, updateAppearance } = useAppearance();

const tabs = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;
</script>

<template>
    <div
        role="radiogroup"
        aria-label="Colour theme"
        class="inline-flex gap-1 rounded-lg bg-muted p-1"
    >
        <button
            v-for="{ value, Icon, label } in tabs"
            :key="value"
            type="button"
            role="radio"
            :aria-checked="appearance === value"
            :class="
                cn(
                    'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                    'focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                    appearance === value
                        ? 'bg-background text-foreground shadow-xs'
                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                )
            "
            @click="updateAppearance(value)"
        >
            <component :is="Icon" class="-ml-1 size-4" />
            <span class="ml-1.5 text-sm">{{ label }}</span>
        </button>
    </div>
</template>
